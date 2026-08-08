<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            // Current / Active STEM Programs
            [
                'code' => 'SIJA',
                'name' => 'Sistem Informasi, Jaringan dan Aplikasi',
                'description' => 'Program keahlian pengembangan perangkat lunak, sistem jaringan komputer, cloud computing, dan komputasi berbasis aplikasi.',
                'sort_order' => 1,
            ],
            [
                'code' => 'TME',
                'name' => 'Teknik Mekatronika',
                'description' => 'Integrasi teknik mesin, elektronika kendali, dan komputer pemrogram otomasi industri serta sistem robotika.',
                'sort_order' => 2,
            ],
            [
                'code' => 'TEI',
                'name' => 'Teknik Elektronika Industri',
                'description' => 'Sistem kendali berbasis mikroprosesor, sensor industri, pneumatik/hidrolik, dan otomasi peralatan manufaktur.',
                'sort_order' => 3,
            ],
            [
                'code' => 'TPTU',
                'name' => 'Teknik Pendingin dan Tata Udara',
                'description' => 'Teknologi sistem refrigerasi komersial, pengondisian udara (HVAC), dan instalasi pendingin industri.',
                'sort_order' => 4,
            ],
            [
                'code' => 'TKR',
                'name' => 'Teknik Kendaraan Ringan Otomotif',
                'description' => 'Pemeliharaan dan perbaikan sistem mekanik, kelistrikan, dan mesin kendaraan otomotif ringan.',
                'sort_order' => 5,
            ],
            [
                'code' => 'TFLM',
                'name' => 'Teknik Fabrikasi Logam dan Manufaktur',
                'description' => 'Teknik penyambungan pengelasan industri, perancangan pola fabrikasi logam, dan manufaktur struktur presisi.',
                'sort_order' => 6,
            ],
            [
                'code' => 'DPIB',
                'name' => 'Desain Permukiman dan Informasi Bangunan',
                'description' => 'Perancangan arsitektur gedung, gambar kerja konstruksi 2D/3D (CAD/BIM), dan pemodelan informasi bangunan.',
                'sort_order' => 7,
            ],
            [
                'code' => 'KGSP',
                'name' => 'Konstruksi Gedung, Sanitasi dan Perawatan',
                'description' => 'Teknik konstruksi bangunan gedung, sistem utilitas sanitasi lingkungan, dan perawatan infrastruktur fisik.',
                'sort_order' => 8,
            ],
            [
                'code' => 'TAV',
                'name' => 'Teknik Audio Video',
                'description' => 'Pengolahan sinyal audio video, transmisi komunikasi data nirkabel, dan sistem peralatan elektronika hiburan.',
                'sort_order' => 9,
            ],
            [
                'code' => 'TKJ',
                'name' => 'Teknik Komputer Jaringan',
                'description' => 'Pengelolaan infrastruktur jaringan LAN/WAN, routing & switching, serta administrasi server sistem operasi.',
                'sort_order' => 10,
            ],

            // Mechanical & Machining Departments
            [
                'code' => 'MK',
                'name' => 'Mesin Konstruksi',
                'description' => 'Spesialisasi operasi, perawatan, dan sistem pemeliharaan alat berat serta peralatan mesin konstruksi lapangan.',
                'sort_order' => 11,
            ],
            [
                'code' => 'TMK',
                'name' => 'Teknik Mesin Perkakas',
                'description' => 'Pembuatan komponen presisi menggunakan mesin bubut, frais, sekrap, dan mesin perkakas manufaktur.',
                'sort_order' => 12,
            ],
            [
                'code' => 'TMP',
                'name' => 'Teknik Permesinan',
                'description' => 'Proses permesinan modern, pemotongan logam presisi tinggi, dan pemrograman mesin perkakas berbasis CNC.',
                'sort_order' => 13,
            ],
            [
                'code' => 'TPFL',
                'name' => 'Teknik Pengelasan dan Fabrikasi Logam',
                'description' => 'Teknik pengelasan tingkat lanjut (SMAW, GMAW, GTAW) dan perakitan struktur fabrikasi baja/logam.',
                'sort_order' => 14,
            ],

            // Automotive Departments
            [
                'code' => 'MO',
                'name' => 'Mesin Otomotif',
                'description' => 'Spesialisasi perbaikan mekanikal mesin pembakaran dalam (internal combustion), transmisi, dan kaki-kaki kendaraan.',
                'sort_order' => 15,
            ],
            [
                'code' => 'TMO',
                'name' => 'Teknik Mekanik Otomotif',
                'description' => 'Diagnosis sistemik kelistrikan dan mekanisme pergerakan roda daya mesin otomotif.',
                'sort_order' => 16,
            ],
            [
                'code' => 'TMPO',
                'name' => 'Teknik Manajemen Perawatan Otomotif',
                'description' => 'Pengelolaan sistem perawatan berkala armada armada kendaraan, manajemen operasional bengkel, dan suku cadang.',
                'sort_order' => 17,
            ],
            [
                'code' => 'TO',
                'name' => 'Teknik Otomotif',
                'description' => 'Dasar rekayasa otomotif, pengujian performa mesin, dan teknologi perkembangan kendaraan bermotor.',
                'sort_order' => 18,
            ],

            // Electrical & Electronics Departments
            [
                'code' => 'ET',
                'name' => 'Elektro Tenaga',
                'description' => 'Teknologi distribusi tenaga listrik tegangan tinggi/menengah dan pengoperasian peralatan gardu induk kelistrikan.',
                'sort_order' => 19,
            ],
            [
                'code' => 'EK',
                'name' => 'Elektronika Komunikasi',
                'description' => 'Penerapan sinyal nirkabel, pemancar radio frekuensi (RF), dan sistem instrumen telekomunikasi elektronik.',
                'sort_order' => 20,
            ],
            [
                'code' => 'LT',
                'name' => 'Listrik Tenaga',
                'description' => 'Instalasi jaringan transmisi listrik dan pengoperasian mesin daya penggerak listrik industri.',
                'sort_order' => 21,
            ],
            [
                'code' => 'EI',
                'name' => 'Elektronika Industri',
                'description' => 'Perancangan papan sirkuit otomasi pabrik, kontrol mikrokontroler, dan rangkaian sistem elektronik otomatis.',
                'sort_order' => 22,
            ],
            [
                'code' => 'EDK',
                'name' => 'Elektronika Daya Kom',
                'description' => 'Penerapan modul semikonduktor pengubah daya cerdas, inverter energi, dan komunikasi data alat ukur.',
                'sort_order' => 23,
            ],
            [
                'code' => 'PTL',
                'name' => 'Pemanfaatan Tenaga Listrik',
                'description' => 'Perancangan dan pengujian sistem pemanfaatan daya listrik untuk bangunan komersial dan gedung penerangan.',
                'sort_order' => 24,
            ],
            [
                'code' => 'ITL',
                'name' => 'Instalasi Tenaga Listrik',
                'description' => 'Pemasangan panel hubung bagi (PHB), sistem pengamanan beban listrik, dan jaringan daya bangunan industri.',
                'sort_order' => 25,
            ],
            [
                'code' => 'TTL',
                'name' => 'Teknik Tenaga Listrik',
                'description' => 'Rekayasa arus kuat pembangkitan listrik, jaringan transmisi, dan kendali sistem beban tenaga listrik.',
                'sort_order' => 26,
            ],
            [
                'code' => 'LI',
                'name' => 'Listrik Industri',
                'description' => 'Pemrograman PLC, instalasi motor induksi 3-fase, dan otomasi kendali proses produksi pabrik.',
                'sort_order' => 27,
            ],

            // Civil, Construction & Geomatics Departments
            [
                'code' => 'BA',
                'name' => 'Bangunan Air',
                'description' => 'Perancangan dan konstruksi fisik saluran irigasi, bendungan, pintu air, dan infrastruktur hidrologi keairan.',
                'sort_order' => 28,
            ],
            [
                'code' => 'BG',
                'name' => 'Bangunan Gedung',
                'description' => 'Perencanaan dan pelaksanaan fisik konstruksi struktur pondasi, kolom bertulang, dan rangka arsitektur gedung.',
                'sort_order' => 29,
            ],
            [
                'code' => 'TKB',
                'name' => 'Teknik Konstruksi Bangunan',
                'description' => 'Manajemen proyek sipil, pengujian mutu material bangunan, dan pengawasan fisik konstruksi bangunan.',
                'sort_order' => 30,
            ],
            [
                'code' => 'TKBB',
                'name' => 'Teknik Konstruksi Batu dan Beton',
                'description' => 'Pekerjaan perancah acuan, pembesian cetakan, dan teknik perakitan cetak beton bertulang bertingkat.',
                'sort_order' => 31,
            ],
            [
                'code' => 'TKP',
                'name' => 'Teknik Konstruksi dan Perumahan',
                'description' => 'Perancangan tata ruang perumahan, drainase permukiman, dan pelaksanaan konstruksi hunian.',
                'sort_order' => 32,
            ],
            [
                'code' => 'TP',
                'name' => 'Teknik Perkayuan',
                'description' => 'Pengolahan bahan kayu bangunan, fabrikasi mebel/interior presisi, dan analisis struktur elemen kayu.',
                'sort_order' => 33,
            ],
            [
                'code' => 'TSP',
                'name' => 'Teknik Survey dan Pemetaan',
                'description' => 'Pengukuran kontur lahan (theodolite/total station), sistem informasi geografis (GIS), dan pemetaan topografi.',
                'sort_order' => 34,
            ],
            [
                'code' => 'TGB',
                'name' => 'Teknik Gambar Bangunan',
                'description' => 'Penyusunan gambar teknik arsitektural 2D/3D, detail struktur sipil, dan Rencana Anggaran Biaya (RAB).',
                'sort_order' => 35,
            ],
            [
                'code' => 'KJIJ',
                'name' => 'Konstruksi Jalan, Irigasi dan Jembatan',
                'description' => 'Perancangan dan pekerjaan fisik perkerasan jalan raya, struktur jembatan bentang panjang, dan jaringan air irigasi.',
                'sort_order' => 36,
            ],
            [
                'code' => 'KPBS',
                'name' => 'Konstruksi Perawatan Bangunan Sipil',
                'description' => 'Inspeksi keandalan bangunan, prosedur perbaikan retak struktur, dan perawatan periodik sarana publik sipil.',
                'sort_order' => 37,
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
