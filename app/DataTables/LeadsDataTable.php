<?php

namespace App\DataTables;

use Carbon\Carbon;
use App\Models\Lead;
use App\Models\LeadAgent;
use App\Models\LeadStatus;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\DataTables\BaseDataTable;
use Illuminate\Database\Eloquent\Model;
use Yajra\DataTables\Html\Button;
use Yajra\DataTables\Html\Column;
use Illuminate\Support\Facades\DB;

class LeadsDataTable extends BaseDataTable
{

    private $editLeadPermission;
    private $viewLeadFollowUpPermission;
    private $deleteLeadPermission;
    private $addFollowUpPermission;
    private $changeLeadStatusPermission;
    private $viewLeadPermission;
    private $myAgentId;

    /**
     * @var LeadStatus[]|\Illuminate\Database\Eloquent\Collection
     */
    private $status;

    public function __construct()
    {
        parent::__construct();
        $this->editLeadPermission = user()->permission('edit_lead');
        $this->deleteLeadPermission = user()->permission('delete_lead');
        $this->viewLeadPermission = user()->permission('view_lead');
        $this->addFollowUpPermission = user()->permission('add_lead_follow_up');
        $this->changeLeadStatusPermission = user()->permission('change_lead_status');
        $this->viewLeadFollowUpPermission = user()->permission('view_lead_follow_up');
        $this->status = LeadStatus::get();
        $this->myAgentId = LeadAgent::where('user_id', user()->id)->first();
    }

    /**
     * Build DataTable class.
     *
     * @param mixed $query Results from query() method.
     * @return \Yajra\DataTables\DataTableAbstract
     */
    public function dataTable($query)
    {
        $currentDate = Carbon::now(company()->timezone)->translatedFormat('Y-m-d');
        $status = $this->status;

        $datatables = datatables()->eloquent($query);
        $datatables->addIndexColumn();
        $datatables->addColumn('check', function ($row) {
            return '<input type="checkbox" class="select-table-row" id="datatable-row-' . $row->id . '"  name="datatable_ids[]" value="' . $row->id . '" onclick="dataTableRowCheck(' . $row->id . ')">';
        });
        $datatables->addColumn('action', function ($row) {
            $action = '<div class="task_view">

                    <div class="dropdown">
                        <a class="task_view_more d-flex align-items-center justify-content-center dropdown-toggle" type="link"
                            id="dropdownMenuLink-' . $row->id . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="icon-options-vertical icons"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuLink-' . $row->id . '" tabindex="0">';

            $action .= '<a href="' . route('leads.show', [$row->id]) . '" class="dropdown-item"><i class="fa fa-eye mr-2"></i>' . __('app.view') . '</a>';

            if (
                $this->editLeadPermission == 'all'
                || ($this->editLeadPermission == 'added' && user()->id == $row->added_by)
                || ($this->editLeadPermission == 'owned' && !is_null($row->agent_id) && user()->id == $row->leadAgent->user->id)
                || ($this->editLeadPermission == 'both' && ((!is_null($row->agent_id) && user()->id == $row->leadAgent->user->id)
                        || user()->id == $row->added_by))
            ) {
                $action .= '<a class="dropdown-item" href="' . route('leads.edit', [$row->id]) . '">
                                <i class="fa fa-edit mr-2"></i>
                                ' . trans('app.edit') . '
                            </a>';
            }

            if (
                $this->deleteLeadPermission == 'all'
                || ($this->deleteLeadPermission == 'added' && user()->id == $row->added_by)
                || ($this->deleteLeadPermission == 'owned' && !is_null($row->agent_id) && user()->id == $row->leadAgent->user->id)
                || ($this->deleteLeadPermission == 'both' && ((!is_null($row->agent_id) && user()->id == $row->leadAgent->user->id)
                        || user()->id == $row->added_by))
            ) {
                $action .= '<a class="dropdown-item delete-table-row" href="javascript:;" data-id="' . $row->id . '">
                        <i class="fa fa-trash mr-2"></i>
                        ' . trans('app.delete') . '
                    </a>';
            }


            if ($row->client_id == null || $row->client_id == '') {
                $action .= '<a class="dropdown-item" href="' . route('clients.create') . '?lead=' . $row->id . '">
                                <i class="fa fa-user mr-2"></i>
                                ' . trans('modules.lead.changeToClient') . '
                            </a>';
            }

            if (($this->addFollowUpPermission == 'all' || ($this->addFollowUpPermission == 'added' && user()->id == $row->added_by)) && $row->next_follow_up == 'yes') {
                $action .= '<a onclick="followUp(' . $row->id . ')" class="dropdown-item" href="javascript:;">
                                <i class="fa fa-thumbs-up mr-2"></i>
                                ' . trans('modules.lead.addFollowUp') . '
                            </a>';
            }

            $action .= '</div>
                    </div>
                </div>';

            return $action;
        });
        $datatables->addColumn('employee_name', function ($row) {
            if (!is_null($row->agent_id)) {
                return $row->leadAgent->user->name;
            }
        });
        $datatables->addColumn('mobile', function ($row) {
            if ($row->mobile != '') {
                return '<a href="tel:' . $row->mobile . '" class="text-darkest-grey"><u>' . $row->mobile . '</u></a>';
            }

            return '--';
        });
        $datatables->addColumn('export_mobile', function ($row) {
            return $row->mobile;
        });
        $datatables->addColumn('export_email', function ($row) {
            return $row->client_email;
        });
        $datatables->addColumn('lead_value', function ($row) {
            return currency_format($row->value, $row->currency_id);
        });
        $datatables->addColumn('lead', function ($row) {
            return $row->client_name;
        });

        $datatables->addColumn('category_name', function ($row) {

            if (!is_null($row->category_id)) {
                return $row->category->category_name;
            }
        });

        $datatables->addColumn('status', function ($row) use ($status) {
            $action = '--';

            if ($this->changeLeadStatusPermission == 'all') {

                $statusLi = '--';

                foreach ($status as $st) {
                    if ($row->status_id == $st->id) {
                        $selected = 'selected';
                    }
                    else {
                        $selected = '';
                    }

                    $statusLi .= '<option data-content="<i class=\'fa fa-circle\' style=\'color: ' . $st->label_color . '\'></i> ' . $st->type . '"' . $selected . ' value="' . $st->id . '">' . $st->type . '</option>';
                }

                $action = '<select class="form-control statusChange" name="statusChange" onchange="changeStatus( ' . $row->id . ', this.value)">
                        ' . $statusLi . '
                    </select>';

            }
            else {
                foreach ($status as $st) {
                    if ($row->status_id == $st->id) {
                        $action = $st->type;
                    }
                }
            }


            return $action;
        });
        $datatables->addColumn('leadStatus', function ($row) use ($status) {
            $leadStatus = '';

            foreach ($status as $st) {
                if ($row->status_id == $st->id) {
                    $leadStatus = $st->type;
                }
            }

            return $leadStatus;
        });


        $datatables->editColumn('client_name', function ($row) {
            if ($row->client_id != null && $row->client_id != '') {
                $label = '<label class="badge badge-secondary">' . __('app.client') . '</label>';
            }
            else {
                $label = '';
            }

            $client_name = ($row->salutation ? $row->salutation->label() . ' ' : '') . $row->client_name;

            return '
                        <div class="media-bod1y">
                    <h5 class="mb-0 f-13 "><a href="' . route('leads.show', [$row->id]) . '">' . $client_name . '</a></h5>
                    <p class="mb-0">' . $label . '</p>
                    <p class="mb-0 f-12 text-dark-grey">
                    '.$row->company_name.'
                </p>
                    </div>
                  ';
        });
        $datatables->editColumn('next_follow_up_date', function ($row) use ($currentDate) {
            if ($this->viewLeadFollowUpPermission != 'none') {
                // code...
                if ($row->next_follow_up_date != null && $row->next_follow_up_date != '') {
                    $date = Carbon::parse($row->next_follow_up_date)->translatedFormat($this->company->date_format . ' ' . $this->company->time_format);
                }
                else {
                    $date = '--';
                }

                if ($row->next_follow_up_date < $currentDate && $row->next_follow_up_status == 'incomplete' && $date != '--') {
                    return $date . '<br><label class="badge badge-danger">' . __('app.pending') . '</label>';
                }

                return $date;
            }
        });
        $datatables->editColumn('created_at', function ($row) {
            return $row->created_at->translatedFormat($this->company->date_format);
        });
        $datatables->editColumn('agent_name', function ($row) {

            if (!is_null($row->agent_id)) {
                return view('components.employee-image', [
                    'user' => $row->leadAgent->user
                ]);
            }

            return '--';
        });
        $datatables->smart(false);
        $datatables->setRowId(function ($row) {
            return 'row-' . $row->id;
        });
        $datatables->removeColumn('status_id');
        $datatables->removeColumn('client_id');
        $datatables->removeColumn('source');
        $datatables->removeColumn('next_follow_up');
        $datatables->removeColumn('statusName');
        $datatables->removeColumn('statusName');

        $customFieldColumns = CustomField::customFieldData($datatables, Lead::CUSTOM_FIELD_MODEL);

        $datatables->rawColumns(array_merge(['status', 'action', 'client_name', 'next_follow_up_date', 'agent_name', 'check', 'mobile'], $customFieldColumns));

        return $datatables;
    }

    /**
     * @param Lead $model
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function query(Lead $model)
    {
        $lead = $model->with(['leadAgent', 'leadAgent.user', 'category'])
            ->select(
                'leads.id',
                'leads.agent_id',
                'leads.mobile',
                'leads.added_by',
                'leads.client_id',
                'leads.next_follow_up',
                'leads.salutation',
                'leads.category_id',
                'leads.value',
                'client_name',
                'client_email',
                'company_name',
                'lead_status.type as statusName',
                'status_id',
                'leads.created_at',
                'leads.updated_at',
                'lead_sources.type as source',
                'users.name as agent_name',
                'users.image',
                DB::raw("(select next_follow_up_date from lead_follow_up where lead_id = leads.id and leads.next_follow_up  = 'yes' and status = 'incomplete' ORDER BY next_follow_up_date asc limit 1) as next_follow_up_date"),
                DB::raw("(select lead_follow_status.status from lead_follow_up as lead_follow_status where lead_id = leads.id and leads.next_follow_up  = 'yes' and status = 'incomplete' ORDER BY next_follow_up_date asc limit 1) as next_follow_up_status")
            )
            ->leftJoin('lead_status', 'lead_status.id', 'leads.status_id')
            ->leftJoin('lead_agents', 'lead_agents.id', 'leads.agent_id')
            ->leftJoin('users', 'users.id', 'lead_agents.user_id')
            ->leftJoin('lead_sources', 'lead_sources.id', 'leads.source_id');

        if ($this->request()->followUp != 'all' && $this->request()->followUp != '') {
            $lead = $lead->leftJoin('lead_follow_up', 'lead_follow_up.lead_id', 'leads.id');

            if ($this->request()->followUp == 'yes') {
                $lead = $lead->where('leads.next_follow_up', 'yes');
            }
            else {
                $lead = $lead->where('leads.next_follow_up', 'no');
            }

        }

        if (!is_null($this->request()->min) || !is_null($this->request()->max)) {
            $min = $this->request()->min;
            $max = $this->request()->max;

            $lead = $lead->whereBetween('value', [$min, $max]);
        }

        if ($this->request()->type != 'all' && $this->request()->type != '') {

            if ($this->request()->type == 'lead') {
                $lead = $lead->whereNull('client_id');
            }
            else {
                $lead = $lead->whereNotNull('client_id');
            }
        }

        if ($this->request()->startDate !== null && $this->request()->startDate != 'null' && $this->request()->startDate != '' && request()->date_filter_on == 'created_at') {
            $startDate = Carbon::createFromFormat($this->company->date_format, $this->request()->startDate)->toDateString();

            $lead = $lead->having(DB::raw('DATE(leads.`created_at`)'), '>=', $startDate);
        }

        if ($this->request()->startDate !== null && $this->request()->startDate != 'null' && $this->request()->startDate != '' && request()->date_filter_on == 'next_follow_up_date') {
            $startDate = Carbon::createFromFormat($this->company->date_format, $this->request()->startDate)->toDateString();

            $lead = $lead->having(DB::raw('DATE(`next_follow_up_date`)'), '>=', $startDate);
        }

        if ($this->request()->endDate !== null && $this->request()->endDate != 'null' && $this->request()->endDate != '' && request()->date_filter_on == 'created_at') {
            $endDate = Carbon::createFromFormat($this->company->date_format, $this->request()->endDate)->toDateString();
            $lead = $lead->having(DB::raw('DATE(leads.`created_at`)'), '<=', $endDate);
        }

        if ($this->request()->endDate !== null && $this->request()->endDate != 'null' && $this->request()->endDate != '' && request()->date_filter_on == 'next_follow_up_date') {
            $endDate = Carbon::createFromFormat($this->company->date_format, $this->request()->endDate)->toDateString();
            $lead = $lead->having(DB::raw('DATE(`next_follow_up_date`)'), '<=', $endDate);
        }

        if ($this->request()->startDate !== null && $this->request()->startDate != 'null' && $this->request()->startDate != '' && request()->date_filter_on == 'updated_at') {
            $startDate = Carbon::createFromFormat($this->company->date_format, $this->request()->startDate)->toDateString();

            $lead = $lead->having(DB::raw('DATE(leads.`updated_at`)'), '>=', $startDate);
        }

        if ($this->request()->endDate !== null && $this->request()->endDate != 'null' && $this->request()->endDate != '' && request()->date_filter_on == 'updated_at') {
            $endDate = Carbon::createFromFormat($this->company->date_format, $this->request()->endDate)->toDateString();
            $lead = $lead->having(DB::raw('DATE(leads.`updated_at`)'), '<=', $endDate);
        }

        if (($this->request()->agent != 'all' && $this->request()->agent != '') || $this->viewLeadPermission == 'added') {
            $lead = $lead->where(function ($query) {
                if ($this->request()->agent != 'all' && $this->request()->agent != '') {
                    $query->where('agent_id', $this->request()->agent);
                }

                if ($this->viewLeadPermission == 'added') {
                    $query->orWhere('leads.added_by', user()->id);
                }
            });
        }

        if ($this->viewLeadPermission == 'owned' && !is_null($this->myAgentId)) {
            $lead = $lead->where(function ($query) {
                $query->where('agent_id', $this->myAgentId->id);
            });
        }

        if ($this->viewLeadPermission == 'both') {
            $lead = $lead->where(function ($query) {
                if (!is_null($this->myAgentId)) {
                    $query->where('agent_id', $this->myAgentId->id);
                }

                $query->orWhere('leads.added_by', user()->id);
            });
        }

        if ($this->request()->category_id != 'all' && $this->request()->category_id != '') {
            $lead = $lead->where('category_id', $this->request()->category_id);
        }

        if ($this->request()->source_id != 'all' && $this->request()->source_id != '') {
            $lead = $lead->where('source_id', $this->request()->source_id);
        }

        if ($this->request()->status_id != 'all' && $this->request()->status_id != '') {
            $lead = $lead->where('status_id', $this->request()->status_id);
        }

        if ($this->request()->searchText != '') {
            $lead = $lead->where(function ($query) {
                $query->where('leads.client_name', 'like', '%' . request('searchText') . '%')
                    ->orWhere('leads.client_email', 'like', '%' . request('searchText') . '%')
                    ->orWhere('leads.company_name', 'like', '%' . request('searchText') . '%')
                    ->orwhere('leads.mobile', 'like', '%' . request('searchText') . '%');
            });
        }

        return $lead->groupBy('leads.id');
    }

    /**
     * Optional method if you want to use html builder.
     *
     * @return \Yajra\DataTables\Html\Builder
     */
    public function html()
    {
        $dataTable = $this->setBuilder('leads-table', 2)
            ->parameters([
                'initComplete' => 'function () {
                   window.LaravelDataTables["leads-table"].buttons().container()
                    .appendTo("#table-actions")
                }',
                'fnDrawCallback' => 'function( oSettings ) {
                    $("body").tooltip({
                        selector: \'[data-toggle="tooltip"]\'
                    });
                    $(".statusChange").selectpicker();
                }',
            ]);

        if (canDataTableExport()) {
            $dataTable->buttons(Button::make(['extend' => 'excel', 'text' => '<i class="fa fa-file-export"></i> ' . trans('app.exportExcel')]));
        }

        return $dataTable;
    }

    /**
     * Get columns.
     *
     * @return array
     */
    protected function getColumns()
    {

        $data = [

            'check' => [
                'title' => '<input type="checkbox" name="select_all_table" id="select-all-table" onclick="selectAllTable(this)">',
                'exportable' => false,
                'orderable' => false,
                'searchable' => false
            ],
            '#' => ['data' => 'DT_RowIndex', 'orderable' => false, 'searchable' => false, 'visible' => false, 'title' => '#'],
            __('app.id') => ['data' => 'id', 'name' => 'id', 'title' => __('app.id'), 'visible' => showId()],
            __('app.name') => ['data' => 'client_name', 'name' => 'client_name', 'exportable' => false, 'title' => __('app.name')],
            __('app.lead') => ['data' => 'lead', 'name' => 'client_name', 'visible' => false, 'title' => __('app.lead')],
            __('app.email') . ' ' . __('modules.lead.email') => ['data' => 'export_email', 'name' => 'email', 'title' => __('app.lead') . ' ' . __('modules.lead.email'), 'exportable' => true, 'visible' => false],
            __('modules.lead.leadCategory') => ['data' => 'category_name', 'name' => 'category_name', 'exportable' => true, 'visible' => false, 'title' => __('modules.lead.leadCategory')],
            __('modules.lead.email') => ['data' => 'client_email', 'name' => 'client_email', 'title' => __('modules.lead.email')],
            __('modules.lead.mobile') => ['data' => 'mobile', 'name' => 'mobile', 'title' => __('modules.lead.mobile'), 'exportable' => false],
            __('app.lead') . ' ' . __('modules.lead.mobile') => ['data' => 'export_mobile', 'name' => 'mobile', 'title' => __('app.lead') . ' ' . __('modules.lead.mobile'), 'exportable' => true, 'visible' => false],
            __('app.lead') .' '. __('app.value') => ['data' => 'lead_value', 'name' => 'value', 'title' => __('app.lead') .' '. __('app.value'), 'exportable' => false],
            __('app.createdOn') => ['data' => 'created_at', 'name' => 'created_at', 'title' => __('app.createdOn')],
            __('modules.lead.nextFollowUp') => ['data' => 'next_follow_up_date', 'name' => 'next_follow_up_date', 'searchable' => false, 'exportable' => ($this->viewLeadFollowUpPermission != 'none'), 'title' => __('modules.lead.nextFollowUp'), 'visible' => ($this->viewLeadFollowUpPermission != 'none')],
            __('modules.lead.leadAgent') => ['data' => 'agent_name', 'name' => 'users.name', 'exportable' => false, 'title' => __('modules.lead.leadAgent')],
            __('app.leadAgent') => ['data' => 'employee_name', 'name' => 'users.name', 'visible' => false, 'title' => __('app.leadAgent')],
            __('app.status') => ['data' => 'status', 'name' => 'status', 'exportable' => false, 'title' => __('app.status')],
            __('app.menu.leadStatus') => ['data' => 'leadStatus', 'name' => 'leadStatus', 'visible' => false, 'orderable' => false, 'searchable' => false, 'title' => __('app.status')]
        ];

        $action = [
            Column::computed('action', __('app.action'))
                ->exportable(false)
                ->printable(false)
                ->orderable(false)
                ->searchable(false)
                ->addClass('text-right pr-20')
        ];


        return array_merge($data, CustomFieldGroup::customFieldsDataMerge(new Lead()), $action);

    }

}
