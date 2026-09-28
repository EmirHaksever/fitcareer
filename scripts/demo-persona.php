<?php

declare(strict_types=1);

/*
 * Fictional demo candidate. The demo login is public (portfolio), so nothing here
 * may belong to a real person: no real name, school, grade, employer or contact.
 */

if (! function_exists('buildDemoCvDocx')) {
    /**
     * Returns DOCX bytes built from the test template with the given paragraphs.
     *
     * @param  list<string>  $lines
     */
    function buildDemoCvDocx(array $lines, string $templatePath): string
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'fitcareer-demo-cv-');
        if ($temporaryPath === false || ! copy($templatePath, $temporaryPath)) {
            throw new RuntimeException('Demo CV şablonu hazırlanamadı.');
        }

        $zip = new ZipArchive();
        if ($zip->open($temporaryPath) !== true) {
            @unlink($temporaryPath);
            throw new RuntimeException('Demo CV DOCX şablonu açılamadı.');
        }

        $paragraphs = implode('', array_map(
            fn (string $line): string => '<w:p><w:r><w:t xml:space="preserve">'.htmlspecialchars($line, ENT_XML1).'</w:t></w:r></w:p>',
            $lines,
        ));

        $zip->deleteName('word/document.xml');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$paragraphs.'<w:sectPr/></w:body></w:document>');
        $zip->close();

        $contents = file_get_contents($temporaryPath);
        @unlink($temporaryPath);

        if ($contents === false) {
            throw new RuntimeException('Demo CV okunamadı.');
        }

        return $contents;
    }
}

return [
    'user_name' => 'Deniz Aydın',
    'cv_filename' => 'Deniz Aydin - Demo CV.docx',
    'cover_letter' => 'Stajlarımda Laravel ile REST API ve test yazma deneyimi edindim; bu pozisyonda backend becerilerimi geliştirmek isterim. (Kurgusal demo başvurusu.)',
    'cv_lines' => [
        'Deniz Aydın',
        'Junior Backend Developer',
        'Bu CV, FitCareer demosu için hazırlanmış kurgusal bir örnektir.',
        'Özet',
        'PHP, Laravel ve MySQL ile REST API geliştiren; test yazmayı önemseyen junior backend geliştirici.',
        'Yetenekler',
        'PHP, Laravel, MySQL, REST API, JavaScript, Git, SQL',
        'Deneyim',
        'Örnek Yazılım A.Ş. — Backend Geliştirici Stajyeri (2025)',
        'Demo Teknoloji Ltd. — Yazılım Geliştirme Stajyeri (2025)',
        'Eğitim',
        'Örnek Üniversitesi — Bilgisayar Mühendisliği, Lisans',
        'Projeler',
        'Görev Takip API — Laravel, MySQL, REST API',
        'Kütüphane Yönetim Paneli — PHP, Laravel, MySQL',
    ],
    'profile' => [
        'headline' => 'Junior Backend Developer',
        'summary' => 'Kurgusal demo aday. PHP, Laravel ve MySQL ile REST API geliştiren, test yazmayı önemseyen junior backend geliştirici.',
        'city' => 'İzmir',
        'country' => 'Türkiye',
        'open_to_work' => true,
        'desired_position' => 'Backend Developer',
        'desired_salary_min' => 45000,
        'desired_salary_max' => 65000,
        'work_preference' => 'hybrid',
        'years_of_experience' => 1,
        'linkedin_url' => null,
        'github_url' => null,
        'portfolio_url' => null,
    ],
    'experiences' => [
        [
            'company_name' => 'Örnek Yazılım A.Ş.',
            'position_title' => 'Backend Geliştirici Stajyeri',
            'employment_type' => 'internship',
            'location' => 'İzmir',
            'is_current' => false,
            'start_date' => '2025-07-01',
            'end_date' => '2025-09-01',
            'description' => 'Laravel ile REST API uç noktaları ve PHPUnit testleri yazdı. (Kurgusal demo deneyimi.)',
        ],
        [
            'company_name' => 'Demo Teknoloji Ltd.',
            'position_title' => 'Yazılım Geliştirme Stajyeri',
            'employment_type' => 'internship',
            'location' => 'İzmir',
            'is_current' => false,
            'start_date' => '2025-01-01',
            'end_date' => '2025-03-01',
            'description' => 'MySQL sorgu iyileştirmeleri ve yönetim paneli ekranları üzerinde çalıştı. (Kurgusal demo deneyimi.)',
        ],
    ],
    'educations' => [
        [
            'school_name' => 'Örnek Üniversitesi',
            'degree' => 'Lisans',
            'field_of_study' => 'Bilgisayar Mühendisliği',
            'start_date' => '2021-09-01',
            'end_date' => '2025-06-01',
            'is_current' => false,
            'grade' => null,
        ],
    ],
    'projects' => [
        [
            'title' => 'Görev Takip API',
            'description' => 'Ekiplerin görev atayıp durum takibi yaptığı, token ile korunan REST API. (Kurgusal demo projesi.)',
            'project_url' => null,
            'repository_url' => null,
            'start_date' => '2025-03-01',
            'end_date' => '2025-05-01',
            'technologies' => ['Laravel', 'MySQL', 'REST API'],
        ],
        [
            'title' => 'Kütüphane Yönetim Paneli',
            'description' => 'Kitap, üye ve ödünç işlemlerinin yönetildiği yönetim paneli. (Kurgusal demo projesi.)',
            'project_url' => null,
            'repository_url' => null,
            'start_date' => '2024-10-01',
            'end_date' => '2024-12-01',
            'technologies' => ['PHP', 'Laravel', 'MySQL'],
        ],
    ],
];
