<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            [
                'code' => 'SIJA',
                'name' => 'Sistem Informasi, Jaringan dan Aplikasi',
                'description' => 'Jurusan Rekayasa Perangkat Lunak, Jaringan Komputer, Cloud Computing, dan Pengembangan Aplikasi Web/Mobile.',
                'sort_order' => 1,
            ],
            [
                'code' => 'TME',
                'name' => 'Teknik Mekatronika',
                'description' => 'Integrasi Teknik Mesin, Elekronika, dan Pemrograman Otomasi Industri / Robotika.',
                'sort_order' => 2,
            ],
            [
                'code' => 'TEI',
                'name' => 'Teknik Elektronika Industri',
                'description' => 'Penerapan Sistem Kontrol Elektronika, Sensor, Mikrokontroler, dan Instrumentasi Industri.',
                'sort_order' => 3,
            ],
            [
                'code' => 'TPTU',
                'name' => 'Teknik Pendingin dan Tata Udara',
                'description' => 'Teknologi Refrigerasi, Sistem HVAC, dan Tata Udara Komersial/Industri.',
                'sort_order' => 4,
            ],
            [
                'code' => 'TKR',
                'name' => 'Teknik Kendaraan Ringan Otomotif',
                'description' => 'Pemeliharaan dan Perbaikan Sistem Otomotif Modern dan Mesin Kendaraan Ringan.',
                'sort_order' => 5,
            ],
            [
                'code' => 'TFLM',
                'name' => 'Teknik Fabrikasi Logam dan Manufaktur',
                'description' => 'Pengelasan Industri, Perancangan Konstruksi Logam, dan Teknik Manufaktur.',
                'sort_order' => 6,
            ],
            [
                'code' => 'DPIB',
                'name' => 'Desain Permukiman dan Informasi Bangunan',
                'description' => 'Perancangan Arsitektur, Desain Bangunan 2D/3D (CAD/BIM), dan Pemetaan Wilayah.',
                'sort_order' => 7,
            ],
            [
                'code' => 'KGSP',
                'name' => 'Konstruksi Gedung, Sanitasi dan Perawatan',
                'description' => 'Teknologi Konstruksi Bangunan Gedung, Utilitas Sanitasi, dan Perawatan Infrastruktur.',
                'sort_order' => 8,
            ],
            [
                'code' => 'TAV',
                'name' => 'Teknik Audio Video',
                'description' => 'Pemrosesan Sinyal Audio Video, Sistem Multimedia, dan Peralatan Elektronika Konsumen.',
                'sort_order' => 9,
            ],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                [
                    'name' => $dept['name'],
                    'description' => $dept['description'],
                    'is_active' => true,
                    'sort_order' => $dept['sort_order'],
                ]
            );
        }
    }
}
