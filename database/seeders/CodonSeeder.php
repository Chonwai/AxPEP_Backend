<?php

namespace Database\Seeders;

use App\Models\Codons;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CodonSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $codons = [
            [
                'id' => Str::uuid(),
                'name' => 'Standard Code',
                'codons_number' => '1'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Vertebrate Mitochondrial Code',
                'codons_number' => '2'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Yeast Mitochondrial Code',
                'codons_number' => '3'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Mold, Protozoan, and Coelenterate Mitochondrial Code and the Mycoplasma/Spiroplasma Code',
                'codons_number' => '4'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Invertebrate Mitochondrial Code',
                'codons_number' => '5'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Ciliate, Dasycladacean and Hexamita Nuclear Code',
                'codons_number' => '6'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Echinoderm and Flatworm Mitochondrial Code',
                'codons_number' => '9'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Euplotid Nuclear Code',
                'codons_number' => '10'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Bacterial, Archaeal and Plant Plastid Code',
                'codons_number' => '11'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Alternative Yeast Nuclear Code',
                'codons_number' => '12'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Ascidian Mitochondrial Code',
                'codons_number' => '13'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Alternative Flatworm Mitochondrial Code',
                'codons_number' => '14'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Chlorophycean Mitochondrial Code',
                'codons_number' => '16'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Trematode Mitochondrial Code',
                'codons_number' => '21'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Scenedesmus obliquus Mitochondrial Code',
                'codons_number' => '22'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Thraustochytrium Mitochondrial Code',
                'codons_number' => '23'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Rhabdopleuridae Mitochondrial Code',
                'codons_number' => '24'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Candidate Division SR1 and Gracilibacteria Code',
                'codons_number' => '25'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Pachysolen tannophilus Nuclear Code',
                'codons_number' => '26'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Karyorelict Nuclear Code',
                'codons_number' => '27'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Condylostoma Nuclear Code',
                'codons_number' => '28'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Mesodinium Nuclear Code',
                'codons_number' => '29'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Peritrich Nuclear Code',
                'codons_number' => '30'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Blastocrithidia Nuclear Code',
                'codons_number' => '31'
            ],
            [
                'id' => Str::uuid(),
                'name' => 'Cephalodiscidae Mitochondrial UAA-Tyr Code',
                'codons_number' => '33'
            ],
        ];
        Codons::factory()->createMany($codons);
    }
}
