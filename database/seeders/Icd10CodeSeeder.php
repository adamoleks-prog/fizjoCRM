<?php

namespace Database\Seeders;

use App\Models\Icd10Code;
use Illuminate\Database\Seeder;

/**
 * Zestaw rozpoznań ICD-10 istotnych dla gabinetu fizjoterapeutycznego.
 *
 * To nie jest pełna klasyfikacja ICD-10 (ta liczy kilkanaście tysięcy pozycji).
 * Pełny oficjalny słownik wczytuje się komendą `php artisan icd10:import <plik.csv>`
 * — patrz app/Console/Commands/ImportIcd10Codes.php.
 */
class Icd10CodeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->codes() as $chapter => $codes) {
            foreach ($codes as $code => $name) {
                Icd10Code::updateOrCreate(['code' => $code], [
                    'name' => $name,
                    'chapter' => $chapter,
                ]);
            }
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function codes(): array
    {
        return [
            'Układ mięśniowo-szkieletowy' => [
                'M05' => 'Serododatnie reumatoidalne zapalenie stawów',
                'M06' => 'Inne reumatoidalne zapalenia stawów',
                'M10' => 'Dna moczanowa',
                'M15' => 'Zwyrodnienie wielostawowe',
                'M16' => 'Choroba zwyrodnieniowa stawu biodrowego (koksartroza)',
                'M17' => 'Choroba zwyrodnieniowa stawu kolanowego (gonartroza)',
                'M19' => 'Inne choroby zwyrodnieniowe stawów',
                'M21' => 'Inne nabyte zniekształcenia kończyn',
                'M22' => 'Choroby rzepki',
                'M23' => 'Uszkodzenia wewnętrzne stawu kolanowego',
                'M24' => 'Inne określone uszkodzenia stawów',
                'M25' => 'Inne choroby stawów, niesklasyfikowane gdzie indziej',
                'M25.5' => 'Ból stawu',
                'M40' => 'Kifoza i lordoza',
                'M41' => 'Skolioza',
                'M42' => 'Osteochondroza kręgosłupa',
                'M43' => 'Inne zniekształcające choroby grzbietu',
                'M45' => 'Zesztywniające zapalenie stawów kręgosłupa (ZZSK)',
                'M46' => 'Inne zapalne choroby kręgosłupa',
                'M47' => 'Zmiany zwyrodnieniowe kręgosłupa (spondyloza)',
                'M48' => 'Inne choroby kręgosłupa',
                'M50' => 'Choroby krążków międzykręgowych szyjnych',
                'M51' => 'Inne choroby krążków międzykręgowych',
                'M53' => 'Inne choroby grzbietu, niesklasyfikowane gdzie indziej',
                'M54' => 'Bóle grzbietu',
                'M54.1' => 'Zapalenie korzeni nerwów rdzeniowych',
                'M54.2' => 'Bóle karku (cervicalgia)',
                'M54.3' => 'Rwa kulszowa',
                'M54.4' => 'Bóle krzyża z rwą kulszową (lumboischialgia)',
                'M54.5' => 'Bóle krzyża (lumbago)',
                'M54.6' => 'Ból kręgosłupa piersiowego',
                'M60' => 'Zapalenie mięśni',
                'M62' => 'Inne choroby mięśni',
                'M65' => 'Zapalenie błony maziowej i pochewki ścięgna',
                'M65.3' => 'Palec strzelający',
                'M66' => 'Samoistne pęknięcie błony maziowej i ścięgna',
                'M67' => 'Inne choroby błony maziowej i ścięgna',
                'M70' => 'Choroby tkanek miękkich związane z przeciążeniem i uciskiem',
                'M71' => 'Inne choroby kaletki maziowej',
                'M72' => 'Choroby fibroblastyczne',
                'M75' => 'Uszkodzenia barku',
                'M75.0' => 'Zapalenie torebki stawu ramiennego (bark zamrożony)',
                'M75.1' => 'Zespół mięśni obręczy barkowej (stożka rotatorów)',
                'M75.4' => 'Zespół ciasnoty podbarkowej (impingement)',
                'M76' => 'Entezopatie kończyny dolnej, z wyłączeniem stopy',
                'M77' => 'Inne entezopatie',
                'M77.0' => 'Zapalenie nadkłykcia przyśrodkowego (łokieć golfisty)',
                'M77.1' => 'Zapalenie nadkłykcia bocznego (łokieć tenisisty)',
                'M79' => 'Inne choroby tkanek miękkich',
                'M79.1' => 'Bóle mięśni (mialgia)',
                'M79.2' => 'Nerwoból i zapalenie nerwu',
                'M79.6' => 'Ból kończyny',
                'M80' => 'Osteoporoza ze złamaniem patologicznym',
                'M81' => 'Osteoporoza bez złamania patologicznego',
                'M87' => 'Martwica kości',
                'M99' => 'Uszkodzenia biomechaniczne, niesklasyfikowane gdzie indziej',
            ],

            'Układ nerwowy' => [
                'G20' => 'Choroba Parkinsona',
                'G35' => 'Stwardnienie rozsiane',
                'G43' => 'Migrena',
                'G50' => 'Choroby nerwu trójdzielnego',
                'G51' => 'Choroby nerwu twarzowego',
                'G54' => 'Choroby korzeni rdzeniowych i splotów nerwowych',
                'G55' => 'Ucisk korzeni rdzeniowych i splotów nerwowych',
                'G56' => 'Mononeuropatie kończyny górnej',
                'G56.0' => 'Zespół cieśni nadgarstka',
                'G57' => 'Mononeuropatie kończyny dolnej',
                'G62' => 'Inne polineuropatie',
                'G80' => 'Dziecięce porażenie mózgowe',
                'G81' => 'Porażenie połowicze (hemiplegia)',
                'G82' => 'Porażenie kończyn dolnych i czterokończynowe',
                'G83' => 'Inne zespoły porażenne',
            ],

            'Układ krążenia' => [
                'I61' => 'Krwotok mózgowy',
                'I63' => 'Zawał mózgu',
                'I64' => 'Udar mózgu, nieokreślony jako krwotoczny lub zawałowy',
                'I69' => 'Następstwa chorób naczyń mózgowych',
                'I70' => 'Miażdżyca',
                'I73' => 'Inne choroby naczyń obwodowych',
                'I83' => 'Żylaki kończyn dolnych',
                'I89.0' => 'Obrzęk limfatyczny, niesklasyfikowany gdzie indziej',
            ],

            'Urazy i następstwa urazów' => [
                'S13' => 'Zwichnięcie, skręcenie i naderwanie stawów i więzadeł szyi',
                'S16' => 'Uraz mięśnia i ścięgna na poziomie szyi',
                'S22' => 'Złamanie żebra, mostka i kręgosłupa piersiowego',
                'S32' => 'Złamanie kręgosłupa lędźwiowego i miednicy',
                'S33' => 'Zwichnięcie, skręcenie i naderwanie kręgosłupa lędźwiowego i miednicy',
                'S42' => 'Złamanie na poziomie barku i ramienia',
                'S43' => 'Zwichnięcie, skręcenie i naderwanie stawów obręczy barkowej',
                'S46' => 'Uraz mięśnia i ścięgna na poziomie barku i ramienia',
                'S52' => 'Złamanie przedramienia',
                'S62' => 'Złamanie na poziomie nadgarstka i ręki',
                'S72' => 'Złamanie kości udowej',
                'S73' => 'Zwichnięcie, skręcenie i naderwanie stawu biodrowego',
                'S76' => 'Uraz mięśnia i ścięgna na poziomie biodra i uda',
                'S82' => 'Złamanie podudzia łącznie ze stawem skokowym',
                'S83' => 'Zwichnięcie, skręcenie i naderwanie stawu kolanowego',
                'S83.2' => 'Rozerwanie łąkotki, świeże',
                'S83.5' => 'Skręcenie i naderwanie więzadła krzyżowego kolana',
                'S86' => 'Uraz mięśnia i ścięgna na poziomie podudzia',
                'S92' => 'Złamanie kości stopy, z wyjątkiem stawu skokowego',
                'S93' => 'Zwichnięcie, skręcenie i naderwanie stawu skokowego i stopy',
                'T92' => 'Następstwa urazów kończyny górnej',
                'T93' => 'Następstwa urazów kończyny dolnej',
            ],

            'Objawy i stany ogólne' => [
                'R26' => 'Nieprawidłowości chodu i poruszania się',
                'R29' => 'Inne objawy dotyczące układu nerwowego i mięśniowo-szkieletowego',
                'R52' => 'Ból, niesklasyfikowany gdzie indziej',
                'R53' => 'Złe samopoczucie i zmęczenie',
            ],

            'Rehabilitacja i stany pooperacyjne' => [
                'Z50' => 'Opieka obejmująca postępowanie rehabilitacyjne',
                'Z50.1' => 'Inna fizjoterapia',
                'Z96' => 'Obecność innych czynnościowych implantów (endoprotezy)',
                'Z98' => 'Inne stany pooperacyjne',
            ],

            'Układ oddechowy' => [
                'J44' => 'Przewlekła obturacyjna choroba płuc (POChP)',
                'J45' => 'Astma',
            ],
        ];
    }
}
