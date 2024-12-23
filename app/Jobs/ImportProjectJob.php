<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Project;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\UniversalSearchTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\ProjectActivity;

class ImportProjectJob implements ShouldQueue
{

    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels, UniversalSearchTrait;

    private $row;
    private $columns;
    private $company;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($row, $columns, $company = null)
    {
        $this->row = $row;
        $this->columns = $columns;
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!empty(array_keys($this->columns, 'project_name')) && !empty(array_keys($this->columns, 'start_date'))) {
            $client = null;

            if (!empty(!empty(array_keys($this->columns, 'client_email')))) {
                // user that have client role
                $client = User::where('email', $this->row[array_keys($this->columns, 'client_email')[0]])->whereHas('roles', function ($q) {
                    $q->where('name', 'client');
                })->first();
            }

            DB::beginTransaction();
            try {
                $project = new Project();
                $project->company_id = $this->company?->id;
                $project->project_name = $this->row[array_keys($this->columns, 'project_name')[0]];

                $project->project_summary = !empty(array_keys($this->columns, 'project_summary')) ? $this->row[array_keys($this->columns, 'project_summary')[0]] : null;

                $project->start_date = Carbon::createFromFormat('Y-m-d', $this->row[array_keys($this->columns, 'start_date')[0]])->format('Y-m-d');
                $project->deadline = !empty(array_keys($this->columns, 'deadline')) ? (!empty(trim($this->row[array_keys($this->columns, 'deadline')[0]])) ? Carbon::createFromFormat('Y-m-d', $this->row[array_keys($this->columns, 'deadline')[0]])->format('Y-m-d') : null) : null;

                if (!empty(array_keys($this->columns, 'notes'))) {
                    $project->notes = $this->row[array_keys($this->columns, 'notes')[0]];
                }

                $project->client_id = $client ? $client->id : null;

                $project->project_budget = !empty(array_keys($this->columns, 'project_budget')) ? $this->row[array_keys($this->columns, 'project_budget')[0]] : null;

                $project->currency_id = $this->company?->currency_id;

                $project->status = !empty(array_keys($this->columns, 'status')) ? strtolower(trim($this->row[array_keys($this->columns, 'status')[0]])) : 'not started';

                $project->save();

                $this->logSearchEntry($project->id, $project->project_name, 'projects.show', 'project', $project->company_id);
                $this->logProjectActivity($project->id, 'messages.updateSuccess');
                DB::commit();
            } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                DB::rollBack();
                $this->job->fail(__('messages.invalidDate') . json_encode($this->row, true));
            }
            catch (\Exception $e) {
                DB::rollBack();
                $this->job->fail($e->getMessage());
            }

        }
        else {
            $this->job->fail(__('messages.invalidData') . json_encode($this->row, true));
        }
    }

    public function logProjectActivity($projectId, $text)
    {
        $activity = new ProjectActivity();
        $activity->project_id = $projectId;
        $activity->activity = $text;
        $activity->save();
    }

}
