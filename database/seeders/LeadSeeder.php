<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\LeadAgent;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LeadSeeder extends Seeder
{

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run($companyId)
    {

        $leadAgents = User::select('users.id')
            ->join('employee_details', 'users.id', '=', 'employee_details.user_id')
            ->join('role_user', 'role_user.user_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('roles.name', 'employee')
            ->where('users.company_id', $companyId)
            ->inRandomOrder()
            ->take(3)->get();
       
        $agents = [];

        foreach ($leadAgents as $agent) {
            array_push($agents, ['user_id' => $agent->id, 'company_id' => $companyId]);
        }

        LeadAgent::insert($agents);

        $currencyID = Currency::where('company_id', $companyId)->first()->id;
        $pendingStatus = LeadStatus::where('company_id', $companyId)
            ->where('type', 'pending')
            ->first();

        $randomLeadId = LeadAgent::where('company_id', $companyId)->inRandomOrder()->first()->id;

        $lead = new \App\Models\Lead();
        $lead->company_id = $companyId;
        $lead->agent_id = $randomLeadId;
        $lead->company_name = 'Test Lead';
        $lead->website = 'https://worksuite.biz';
        $lead->address = 'Jaipur, India';
        $lead->client_name = 'John Doe';
        $lead->client_email = 'testing@test.com';
        $lead->mobile = '123456789';
        $lead->status_id = $pendingStatus->id;
        $lead->value = rand(10000, 99999);
        $lead->currency_id = $currencyID;
        $lead->note = 'Quas consectetur, tempor incidunt, aliquid voluptatem, velit mollit et illum, adipisicing ea officia aliquam placeat';
        $lead->save();

    }

}
