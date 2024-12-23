<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Carbon;

class ImportAttendanceJob implements ShouldQueue
{

    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        if (!empty(array_keys($this->columns, 'clock_in_time')) && !empty(array_keys($this->columns, 'email')) && filter_var($this->row[array_keys($this->columns, 'email')[0]], FILTER_VALIDATE_EMAIL)) {

            // user that have employee role
            $user = User::where('email', $this->row[array_keys($this->columns, 'email')[0]])->whereHas('roles', function ($q) {
                $q->where('name', 'employee');
            })->first();

            if (!$user) {
                $this->job->fail(__('messages.employeeNotFound'));
            }
            else {
                DB::beginTransaction();
                try {
                    Attendance::create([
                        'company_id' => $this->company?->id,
                        'user_id' => $user->id,
                        'clock_in_time' => Carbon::createFromFormat('Y-m-d H:i:s', $this->row[array_keys($this->columns, 'clock_in_time')[0]], $this->company?->timezone)->timezone('UTC')->format('Y-m-d H:i:s'),
                        'clock_in_ip' => !empty(array_keys($this->columns, 'clock_in_ip')) ? $this->row[array_keys($this->columns, 'clock_in_ip')[0]] : '127.0.0.1',
                        'clock_out_time' => !empty(array_keys($this->columns, 'clock_out_time')) ? Carbon::createFromFormat('Y-m-d H:i:s', $this->row[array_keys($this->columns, 'clock_out_time')[0]], $this->company?->timezone)->timezone('UTC')->format('Y-m-d H:i:s') : null,
                        'clock_out_ip' => !empty(array_keys($this->columns, 'clock_out_ip')) ? $this->row[array_keys($this->columns, 'clock_out_ip')[0]] : null,
                        'working_from' => !empty(array_keys($this->columns, 'working_from')) ? $this->row[array_keys($this->columns, 'working_from')[0]] : 'office',
                        'late' => !empty(array_keys($this->columns, 'late')) && str($this->row[array_keys($this->columns, 'late')[0]])->lower() == 'yes' ? 'yes' : 'no',
                        'half_day' => !empty(array_keys($this->columns, 'half_day')) && str($this->row[array_keys($this->columns, 'half_day')[0]])->lower() == 'yes' ? 'yes' : 'no',
                    ]);

                    DB::commit();
                } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                    DB::rollBack();
                    $this->job->fail(__('messages.invalidDate') . json_encode($this->row, true));
                } catch (\Exception $e) {
                    DB::rollBack();
                    $this->job->fail($e->getMessage());
                }
            }
        }
        else {
            $this->job->fail(__('messages.invalidData') . json_encode($this->row, true));
        }
    }

}
