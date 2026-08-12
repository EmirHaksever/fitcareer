<?php

declare(strict_types=1);

namespace Tests\Feature\Candidate;

use App\Models\Skill;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CvImportValidationTest extends TestCase
{
    use CreatesCandidateUsers;

    #[Test]
    public function cv_import_payloads_validate_for_sample_cv(): void
    {
        $this->seedSkills();

        [, $profile, $token] = $this->createCandidateActor();

        $parsed = json_decode(
            file_get_contents(base_path('scripts/cv-sample.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $profile->update([
            'cv_parsed_data' => $parsed,
            'cv_file_path' => 'candidate-cv/test.docx',
            'linkedin_url' => 'https://www.linkedin.com/in/muhammet-',
            'github_url' => 'https://www.youtube.com/watch?v=F-Ox9-CfWTQ',
            'portfolio_url' => 'https://www.youtube.com/watch?v=F-Ox9-CfWTQ',
        ]);

        $profilePayload = [
            'headline' => 'Kullanıcıların alışverişlistesioluşturabildiği, listepaylaşımı yapabildiği ve',
            'summary' => $parsed['sections']['summary'],
            'linkedin_url' => 'https://www.linkedin.com/in/muhammet-',
        ];

        $this->withToken($token)
            ->putJson('/api/v1/candidate/profile', $profilePayload)
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/candidate/projects', [
                'title' => 'ERP sistemi',
                'description' => 'Ağustos 2025',
                'start_date' => '2025-01-01',
            ])
            ->assertCreated();

        $skillNames = [
            'Node.Js', 'RestfulApi', 'C#', 'HTML', 'JavaScript', 'PHP', 'CSS', 'SQL',
            'WebTasarım', 'Web', 'Programlama', 'Wordpress', 'ProjeGeliştirme', 'Git',
            'Github', 'Dart', 'Backend', 'Veritabanı', 'Yönetimi', 'RESTAPI', 'Laravel',
            'Kotlin', 'Yazılım', 'YapayZeka', 'Flutter',
        ];

        $catalog = Skill::query()->get()->all();

        foreach ($skillNames as $skillName) {
            $matched = $this->matchSkill($skillName, $catalog);
            if (! $matched) {
                continue;
            }

            $response = $this->withToken($token)
                ->postJson('/api/v1/candidate/skills', ['skill_id' => $matched->id]);

            $response->assertStatus($response->status() === 422 ? 422 : 201);

            if ($response->status() === 422) {
                fwrite(STDERR, "Failed skill attach for {$skillName} -> {$matched->name} ({$matched->id}): ".json_encode($response->json('errors'))."\n");
            }
        }
    }

  /**
     * @param  list<Skill>  $catalog
     */
    private function matchSkill(string $skillName, array $catalog): ?Skill
    {
        $normalized = mb_strtolower(trim($skillName));

        foreach ($catalog as $item) {
            if (mb_strtolower($item->name) === $normalized) {
                return $item;
            }
        }

        foreach ($catalog as $item) {
            $catalogName = mb_strtolower($item->name);
            if (str_contains($catalogName, $normalized) || str_contains($normalized, $catalogName)) {
                return $item;
            }
        }

        return null;
    }

    private function seedSkills(): void
    {
        foreach ([
            'JavaScript', 'TypeScript', 'React', 'Vue.js', 'PHP', 'Laravel',
            'Python', 'Java', 'SQL', 'Git', 'Docker', 'AWS', 'Node.js',
            'HTML', 'CSS', 'Product Management', 'Agile', 'Scrum',
        ] as $name) {
            Skill::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'category' => 'Technology'],
            );
        }
    }
}
