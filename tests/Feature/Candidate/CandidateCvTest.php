<?php

namespace Tests\Feature\Candidate;

use App\Services\Candidate\CvParserService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class CandidateCvTest extends TestCase
{
    use CreatesCandidateUsers;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    #[Test]
    public function candidate_can_upload_valid_docx_cv_and_receive_parser_structure(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $file = $this->makeDocxUploadedFile('Experience'."\n".'Acme Corp');

        $this->withToken($token)
            ->postJson('/api/v1/candidate/cv', ['cv' => $file])
            ->assertOk()
            ->assertJsonPath('data.has_cv', true);

        $profile->refresh();

        $this->assertNotNull($profile->cv_file_path);
        $this->assertIsArray($profile->cv_parsed_data);
        $this->assertArrayHasKey('text', $profile->cv_parsed_data);
        $this->assertArrayHasKey('sections', $profile->cv_parsed_data);
        $this->assertArrayHasKey('source_filename', $profile->cv_parsed_data);
        $this->assertArrayHasKey('parsed_at', $profile->cv_parsed_data);
        $this->assertArrayHasKey('parser_version', $profile->cv_parsed_data);
        $this->assertSame(config('candidate.parser_version'), $profile->cv_parsed_data['parser_version']);
        Storage::disk('local')->assertExists($profile->cv_file_path);
    }

    #[Test]
    public function invalid_cv_mime_is_rejected(): void
    {
        [, , $token] = $this->createCandidateActor();

        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $this->withToken($token)
            ->postJson('/api/v1/candidate/cv', ['cv' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cv']);
    }

    #[Test]
    public function cv_replacement_deletes_previous_file(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        $first = $this->makeDocxUploadedFile('First CV');
        $this->withToken($token)->postJson('/api/v1/candidate/cv', ['cv' => $first])->assertOk();
        $firstPath = $profile->fresh()->cv_file_path;

        $second = $this->makeDocxUploadedFile('Second CV');
        $this->withToken($token)->postJson('/api/v1/candidate/cv', ['cv' => $second])->assertOk();

        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($profile->fresh()->cv_file_path);
    }

    #[Test]
    public function cv_delete_removes_database_fields_and_storage_file(): void
    {
        [, $profile, $token] = $this->createCandidateActor();

        $file = $this->makeDocxUploadedFile('Delete me');
        $this->withToken($token)->postJson('/api/v1/candidate/cv', ['cv' => $file])->assertOk();
        $storedPath = $profile->fresh()->cv_file_path;

        $this->withToken($token)->deleteJson('/api/v1/candidate/cv')->assertOk();

        $profile->refresh();
        $this->assertNull($profile->cv_file_path);
        $this->assertNull($profile->cv_parsed_data);
        Storage::disk('local')->assertMissing($storedPath);
    }

    #[Test]
    public function cv_parser_service_output_is_deterministic_for_same_input(): void
    {
        $path = $this->createDocxOnDisk('Summary'."\n".'John Doe');
        $parser = app(CvParserService::class);

        $first = $parser->parse($path, 'resume.docx');
        $second = $parser->parse($path, 'resume.docx');

        $this->assertSame($first['text'], $second['text']);
        $this->assertSame(array_keys($first['sections']), array_keys($second['sections']));
    }

    #[Test]
    public function candidate_can_download_uploaded_cv(): void
    {
        [, $profile, $token] = $this->createCandidateActor();
        $file = $this->makeDocxUploadedFile('Experience');

        $this->withToken($token)
            ->postJson('/api/v1/candidate/cv', ['cv' => $file])
            ->assertOk();

        $profile->refresh();

        $this->withToken($token)
            ->get('/api/v1/candidate/cv/download')
            ->assertOk()
            ->assertDownload($file->getClientOriginalName());
    }

    private function makeDocxUploadedFile(string $text): UploadedFile
    {
        $path = $this->createDocxOnDisk($text);

        return new UploadedFile(
            $path,
            'resume.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true,
        );
    }

    private function createDocxOnDisk(string $text): string
    {
        $path = storage_path('framework/testing/upload-resume.docx');
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString(
            'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body><w:p><w:r><w:t>'.htmlspecialchars($text, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>',
        );
        $zip->close();

        return $path;
    }
}
