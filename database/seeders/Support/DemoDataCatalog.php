<?php

declare(strict_types=1);

namespace Database\Seeders\Support;

/**
 * Static reference data used by the demo seeders.
 *
 * This is a plain data holder (not an Eloquent model or a Seeder itself) that
 * keeps realistic Turkish/English demo content in one place so the
 * seeder classes stay readable.
 */
class DemoDataCatalog
{
    /**
     * Career "tracks" used to keep candidate skills/experience and job
     * requirements coherent (a backend candidate mostly matches backend jobs,
     * etc). Each track lists the skills that belong to it, in the order they
     * should be attached (first ones are treated as "required" on jobs).
     *
     * @return array<string, array{
     *     skills: list<string>,
     *     job_titles: list<string>,
     *     headlines: list<string>,
     *     category: string,
     *     field_of_study: string,
     *     certification: array{name: string, org: string},
     *     project_title: string,
     * }>
     */
    public static function tracks(): array
    {
        return [
            'backend' => [
                'skills' => ['PHP', 'Laravel', 'MySQL', 'Node.js', 'REST API', 'Docker'],
                'job_titles' => ['Backend Developer', 'Kıdemli Backend Geliştirici', 'PHP Yazılım Geliştirici', 'Backend Yazılım Mühendisi'],
                'headlines' => ['Backend Developer', 'PHP & Laravel Geliştirici', 'Backend Yazılım Mühendisi'],
                'category' => 'engineering',
                'field_of_study' => 'Bilgisayar Mühendisliği',
                'certification' => ['name' => 'AWS Certified Developer – Associate', 'org' => 'Amazon Web Services'],
                'project_title' => 'E-ticaret API Altyapısı',
            ],
            'frontend' => [
                'skills' => ['JavaScript', 'TypeScript', 'React', 'Vue.js', 'HTML5', 'CSS3', 'Tailwind CSS'],
                'job_titles' => ['Frontend Developer', 'React Geliştirici', 'Kıdemli Frontend Mühendisi'],
                'headlines' => ['Frontend Developer', 'React Geliştirici', 'Frontend Yazılım Mühendisi'],
                'category' => 'engineering',
                'field_of_study' => 'Bilgisayar Mühendisliği',
                'certification' => ['name' => 'Meta Front-End Developer', 'org' => 'Meta'],
                'project_title' => 'Müşteri Paneli Arayüz Yenileme',
            ],
            'fullstack' => [
                'skills' => ['JavaScript', 'PHP', 'Laravel', 'React', 'MySQL', 'REST API'],
                'job_titles' => ['Full Stack Developer', 'Full Stack Yazılım Mühendisi', 'Kıdemli Full Stack Geliştirici'],
                'headlines' => ['Full Stack Developer', 'Full Stack Yazılım Mühendisi'],
                'category' => 'engineering',
                'field_of_study' => 'Yazılım Mühendisliği',
                'certification' => ['name' => 'Oracle Certified Professional', 'org' => 'Oracle'],
                'project_title' => 'İç Operasyon Yönetim Paneli',
            ],
            'devops' => [
                'skills' => ['Docker', 'Kubernetes', 'AWS', 'CI/CD', 'Linux', 'Terraform'],
                'job_titles' => ['DevOps Mühendisi', 'Cloud Infrastructure Engineer', 'Site Reliability Engineer'],
                'headlines' => ['DevOps Mühendisi', 'Cloud & Infrastructure Engineer'],
                'category' => 'engineering',
                'field_of_study' => 'Bilgisayar Mühendisliği',
                'certification' => ['name' => 'AWS Certified Solutions Architect', 'org' => 'Amazon Web Services'],
                'project_title' => 'Kubernetes Tabanlı CI/CD Hattı',
            ],
            'data' => [
                'skills' => ['Python', 'SQL', 'Power BI', 'Excel', 'Data Analysis', 'Machine Learning'],
                'job_titles' => ['Veri Analisti', 'Data Scientist', 'Business Intelligence Uzmanı'],
                'headlines' => ['Veri Analisti', 'Data Scientist'],
                'category' => 'data',
                'field_of_study' => 'İstatistik',
                'certification' => ['name' => 'Google Data Analytics', 'org' => 'Google'],
                'project_title' => 'Satış Tahminleme Modeli',
            ],
            'design' => [
                'skills' => ['Figma', 'UI/UX Design', 'Adobe XD', 'Prototyping', 'Wireframing'],
                'job_titles' => ['UI/UX Tasarımcı', 'Ürün Tasarımcısı', 'Kıdemli Grafik Tasarımcı'],
                'headlines' => ['UI/UX Tasarımcı', 'Ürün Tasarımcısı'],
                'category' => 'design',
                'field_of_study' => 'Grafik Tasarım',
                'certification' => ['name' => 'Google UX Design', 'org' => 'Google'],
                'project_title' => 'Mobil Uygulama Kullanıcı Deneyimi Yenileme',
            ],
            'product' => [
                'skills' => ['Agile', 'Scrum', 'Project Management', 'Product Management', 'Jira'],
                'job_titles' => ['Ürün Yöneticisi', 'Proje Yöneticisi', 'Scrum Master'],
                'headlines' => ['Ürün Yöneticisi', 'Proje Yöneticisi'],
                'category' => 'product',
                'field_of_study' => 'İşletme',
                'certification' => ['name' => 'Certified Scrum Master (CSM)', 'org' => 'Scrum Alliance'],
                'project_title' => 'Ürün Yol Haritası ve Sprint Süreci Kurulumu',
            ],
            'marketing' => [
                'skills' => ['SEO', 'Google Ads', 'Social Media Marketing', 'Content Marketing', 'Analytics'],
                'job_titles' => ['Dijital Pazarlama Uzmanı', 'Sosyal Medya Yöneticisi', 'SEO Uzmanı'],
                'headlines' => ['Dijital Pazarlama Uzmanı', 'SEO Uzmanı'],
                'category' => 'marketing',
                'field_of_study' => 'Pazarlama',
                'certification' => ['name' => 'Google Ads Search Certification', 'org' => 'Google'],
                'project_title' => 'Marka Bilinirliği Dijital Kampanyası',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allSkills(): array
    {
        $skills = [];

        foreach (self::tracks() as $track) {
            $skills = array_merge($skills, $track['skills']);
        }

        return array_values(array_unique($skills));
    }

    /**
     * Company profiles (sector + which tracks they hire for).
     *
     * @return list<array{
     *     name: string,
     *     industry: string,
     *     city: string,
     *     size: string,
     *     founded_year: int,
     *     tracks: list<string>,
     *     is_verified: bool,
     *     verification_status: string,
     * }>
     */
    public static function companies(): array
    {
        return [
            [
                'name' => 'Nova Teknoloji A.Ş.',
                'industry' => 'Yazılım / Bilişim',
                'city' => 'İstanbul',
                'size' => '51-200',
                'founded_year' => 2015,
                'tracks' => ['fullstack', 'devops'],
                'is_verified' => true,
                'verification_status' => 'verified',
            ],
            [
                'name' => 'Mavi Ticaret Lojistik A.Ş.',
                'industry' => 'Lojistik / E-ticaret',
                'city' => 'Ankara',
                'size' => '201-500',
                'founded_year' => 2008,
                'tracks' => ['data', 'backend'],
                'is_verified' => true,
                'verification_status' => 'verified',
            ],
            [
                'name' => 'Kristal Finans Teknolojileri',
                'industry' => 'Finans Teknolojileri (Fintech)',
                'city' => 'İstanbul',
                'size' => '11-50',
                'founded_year' => 2019,
                'tracks' => ['backend', 'devops'],
                'is_verified' => true,
                'verification_status' => 'verified',
            ],
            [
                'name' => 'Yeşil Enerji Sistemleri A.Ş.',
                'industry' => 'Enerji',
                'city' => 'İzmir',
                'size' => '201-500',
                'founded_year' => 2004,
                'tracks' => ['product', 'data'],
                'is_verified' => false,
                'verification_status' => 'pending',
            ],
            [
                'name' => 'Anka Dijital Pazarlama',
                'industry' => 'Dijital Pazarlama / Reklamcılık',
                'city' => 'Bursa',
                'size' => '11-50',
                'founded_year' => 2017,
                'tracks' => ['marketing', 'design'],
                'is_verified' => true,
                'verification_status' => 'verified',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function turkishCities(): array
    {
        return ['İstanbul', 'Ankara', 'İzmir', 'Bursa', 'Antalya'];
    }
}
