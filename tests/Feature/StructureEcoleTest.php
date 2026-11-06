<?php

namespace Tests\Feature;

use App\Models\Cycle;
use App\Models\Matiere;
use Database\Seeders\StructureEcoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StructureEcoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_nouvelle_installation_a_ses_cycles_et_matieres(): void
    {
        $this->seed(StructureEcoleSeeder::class);

        $this->assertSame(['Maternelle', 'Primaire', 'Collège', 'Lycée'], Cycle::orderBy('id')->pluck('nom')->all());
        $this->assertTrue(Matiere::where('cycle_id', Cycle::where('nom', 'Primaire')->value('id'))->exists());
    }

    public function test_relancer_l_installation_ne_change_pas_l_ecole_existante(): void
    {
        $this->seed(StructureEcoleSeeder::class);
        Matiere::query()->update(['coefficient' => 4]);
        Cycle::where('nom', 'Lycée')->delete();

        $this->seed(StructureEcoleSeeder::class);

        $this->assertSame(3, Cycle::count());
        $this->assertSame(0, Matiere::where('coefficient', '!=', 4)->count());
    }
}
