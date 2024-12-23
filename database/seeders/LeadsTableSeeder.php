<?php
namespace Database\Seeders;

use App\Models\Lead;
use App\Models\LeadAgent;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\UniversalSearch;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Traits\UniversalSearchTrait;

class LeadsTableSeeder extends Seeder
{
    use UniversalSearchTrait;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run($companyId)
    {
        $count = config('app.seed_record_count');
        $faker = \Faker\Factory::create();
        $leadAgents = $this->getLeadAgent($companyId);
        $leadSources = $this->getLeadSource($companyId);
        $leadStatuses = $this->getLeadStatus($companyId);

        Lead::factory()
            ->count((int)$count)
            ->make()
            ->each(function (Lead $lead) use($companyId, $faker, $leadAgents, $leadSources, $leadStatuses) {
                $lead->company_id = $companyId;
                $lead->agent_id = $faker->randomElement($leadAgents); /* @phpstan-ignore-line */
                $lead->source_id = $faker->randomElement($leadSources); /* @phpstan-ignore-line */
                $lead->status_id = $faker->randomElement($leadStatuses); /* @phpstan-ignore-line */
                $lead->save();
            });
    }

    private function getLeadAgent($companyId)
    {
        return LeadAgent::where('company_id', $companyId)->pluck('id')->toArray();
    }

    private function getLeadStatus($companyId)
    {
        return LeadStatus::where('company_id', $companyId)->pluck('id')->toArray();
    }

    private function getLeadSource($companyId)
    {
        return LeadSource::where('company_id', $companyId)->pluck('id')->toArray();
    }

}
