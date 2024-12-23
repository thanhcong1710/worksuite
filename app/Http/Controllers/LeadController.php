<?php

namespace App\Http\Controllers;

use App\DataTables\LeadFollowupDataTable;
use App\DataTables\LeadGDPRDataTable;
use App\DataTables\LeadNotesDataTable;
use App\DataTables\LeadsDataTable;
use App\DataTables\ProposalDataTable;
use App\Enums\Salutation;
use App\Helper\Reply;
use App\Http\Requests\Admin\Employee\ImportProcessRequest;
use App\Http\Requests\Admin\Employee\ImportRequest;
use App\Http\Requests\CommonRequest;
use App\Http\Requests\FollowUp\StoreRequest as FollowUpStoreRequest;
use App\Http\Requests\Lead\StoreRequest;
use App\Http\Requests\Lead\UpdateRequest;
use App\Imports\LeadImport;
use App\Jobs\ImportLeadJob;
use App\Models\GdprSetting;
use App\Models\Lead;
use App\Models\LeadAgent;
use App\Models\LeadCategory;
use App\Models\LeadCustomForm;
use App\Models\LeadFollowUp;
use App\Models\LeadNote;
use App\Models\LeadProduct;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\Product;
use App\Models\PurposeConsent;
use App\Models\PurposeConsentLead;
use App\Models\User;
use App\Traits\ImportExcel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeadController extends AccountBaseController
{
    use ImportExcel;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.lead';
        $this->middleware(function ($request, $next) {
            abort_403(!in_array('leads', $this->user->modules));
            return $next($request);
        });
    }

    public function index(LeadsDataTable $dataTable)
    {
        $this->viewLeadPermission = $viewPermission = user()->permission('view_lead');

        abort_403(!in_array($viewPermission, ['all', 'added', 'both', 'owned']));

        if (!request()->ajax()) {
            $this->totalLeads = Lead::get();
            $this->categories = LeadCategory::get();
            $this->sources = LeadSource::get();
            $this->status = LeadStatus::get();

            $this->totalClientConverted = $this->totalLeads->filter(function ($value, $key) {
                return $value->client_id != null;
            });

            $this->totalLeads = $this->totalLeads->count();
            $this->totalClientConverted = $this->totalClientConverted->count();

            $this->pendingLeadFollowUps = LeadFollowUp::where(DB::raw('DATE(next_follow_up_date)'), '<=', now()->format('Y-m-d'))
                ->join('leads', 'leads.id', 'lead_follow_up.lead_id')
                ->where('leads.next_follow_up', 'yes')
                ->groupBy('lead_follow_up.lead_id')
                ->get();
            $this->pendingLeadFollowUps = $this->pendingLeadFollowUps->count();

            $this->viewLeadAgentPermission = user()->permission('view_lead_agents');


            $this->leadAgents = LeadAgent::with('user')->whereHas('user', function ($q) {
                $q->where('status', 'active');
            });

            $this->leadAgents = $this->leadAgents->where(function ($q) {
                if ($this->viewLeadAgentPermission == 'all') {
                    $this->leadAgents = $this->leadAgents;
                }
                elseif ($this->viewLeadAgentPermission == 'added') {
                    $this->leadAgents = $this->leadAgents->where('added_by', user()->id);
                }
                elseif ($this->viewLeadAgentPermission == 'owned') {
                    $this->leadAgents = $this->leadAgents->where('user_id', user()->id);
                }
                elseif ($this->viewLeadAgentPermission == 'both') {
                    $this->leadAgents = $this->leadAgents->where('added_by', user()->id)->orWhere('user_id', user()->id);
                }
                else {
                    // This is $this->viewLeadAgentPermission == 'none'
                    $this->leadAgents = [];
                }
            })->get();

        }

        return $dataTable->render('leads.index', $this->data);

    }

    public function show($id)
    {
        $this->lead = Lead::with(['leadAgent', 'leadAgent.user', 'leadStatus', 'products'])->findOrFail($id)->withCustomFields();

        $leadAgentId = ($this->lead->leadAgent != null) ? $this->lead->leadAgent->user->id : 0;

        $this->viewPermission = user()->permission('view_lead');

        abort_403(!(
            $this->viewPermission == 'all'
            || ($this->viewPermission == 'added' && $this->lead->added_by == user()->id)
            || ($this->viewPermission == 'owned' && $this->lead->leadAgent->user->id == user()->id)
            || ($this->viewPermission == 'both' && ($this->lead->added_by == user()->id || $leadAgentId == user()->id))
        ));

        $this->pageTitle = $this->lead->client_name;

        $this->categories = LeadCategory::all();

        $this->productNames = $this->lead->products->pluck('name')->toArray();

        $this->leadFormFields = LeadCustomForm::with('customField')->where('status', 'active')->where('custom_fields_id', '!=', 'null')->get();

        $this->leadId = $id;

        if ($this->lead->getCustomFieldGroupsWithFields()) {
            $this->fields = $this->lead->getCustomFieldGroupsWithFields()->fields;
        }

        $this->deleteLeadPermission = user()->permission('delete_lead');
        $this->view = 'leads.ajax.profile';

        $tab = request('tab');

        switch ($tab) {
        case 'files':
            $this->view = 'leads.ajax.files';
                break;
        case 'follow-up':
            return $this->leadFollowup();
        case 'proposals':
                return $this->proposals();
        case 'notes':
            return $this->notes();
        case 'gdpr':

            $this->consents = PurposeConsent::with(['lead' => function ($query) use ($id) {
                $query->where('lead_id', $id)
                    ->orderBy('created_at', 'desc');
            }])->get();

            $this->gdpr = GdprSetting::first();

                return $this->gdpr();
        default:
            $this->view = 'leads.ajax.profile';
                break;
        }

        if (request()->ajax()) {
            $html = view($this->view, $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->activeTab = $tab ?: 'profile';
        return view('leads.show', $this->data);

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->addPermission = user()->permission('add_lead');
        abort_403(!in_array($this->addPermission, ['all', 'added']));

        $defaultStatus = LeadStatus::where('default', '1')->first();
        $this->columnId = ((request('column_id') != '') ? request('column_id') : $defaultStatus->id);
        $this->leadAgents = LeadAgent::with('user')->whereHas('user', function ($q) {
            $q->where('status', 'active');
        })->get();

        $this->leadAgentArray = $this->leadAgents->pluck('user_id')->toArray();

        if ((in_array(user()->id, $this->leadAgentArray))) {
            $this->myAgentId = $this->leadAgents->filter(function ($value, $key) {
                return $value->user_id == user()->id;
            })->first()->id;
        }

        $lead = new Lead();

        if ($lead->getCustomFieldGroupsWithFields()) {
            $this->fields = $lead->getCustomFieldGroupsWithFields()->fields;
        }

        $this->products = Product::all();
        $this->sources = LeadSource::all();
        $this->status = LeadStatus::all();
        $this->categories = LeadCategory::all();
        $this->countries = countries();
        $this->pageTitle = __('modules.lead.createTitle');
        $this->salutations = Salutation::cases();

        if (request()->ajax()) {
            $html = view('leads.ajax.create', $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'leads.ajax.create';
        return view('leads.create', $this->data);

    }

    /**
     * @param StoreRequest $request
     * @return array|void
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    public function store(StoreRequest $request)
    {
        $this->addPermission = user()->permission('add_lead');

        abort_403(!in_array($this->addPermission, ['all', 'added']));

        $existingUser = User::select('id')
            ->whereHas('roles', function ($q) {
                        $q->where('name', 'client');
            })->where('company_id', company()->id)
            ->where('email', $request->client_email)
            ->whereNotNull('email')
            ->first();

            $lead = new Lead();
            $lead->company_name = $request->company_name;
            $lead->website = $request->website;
            $lead->address = $request->address;
            $lead->cell = $request->cell;
            $lead->office = $request->office;
            $lead->city = $request->city;
            $lead->state = $request->state;
            $lead->country = $request->country;
            $lead->postal_code = $request->postal_code;
            $lead->salutation = $request->salutation;
            $lead->client_name = $request->client_name;
            $lead->client_email = $request->client_email;
            $lead->mobile = $request->mobile;
            $lead->note = trim_editor($request->note);
            $lead->next_follow_up = $request->next_follow_up;
            $lead->agent_id = $request->agent_id;
            $lead->source_id = $request->source_id;
            $lead->category_id = $request->category_id;
            $lead->client_id = $existingUser?->id;
            $lead->status_id = $request->status;
            $lead->value = ($request->value) ?: 0;
            $lead->currency_id = $this->company->currency_id;
            $lead->save();

        if (!is_null($request->product_id)) {

            $products = $request->product_id;

            foreach($products as $product)
            {
                $leadProduct = new LeadProduct();
                $leadProduct->lead_id = $lead->id;
                $leadProduct->product_id = $product;
                $leadProduct->save();
            }
        }

        // To add custom fields data
        if ($request->custom_fields_data) {
            $lead->updateCustomFieldData($request->custom_fields_data);
        }

            // Log search
            $this->logSearchEntry($lead->id, $lead->client_name, 'leads.show', 'lead');

        if ($lead->client_email) {
            $this->logSearchEntry($lead->id, $lead->client_email, 'leads.show', 'lead');
        }

        if (!is_null($lead->company_name)) {
            $this->logSearchEntry($lead->id, $lead->company_name, 'leads.show', 'lead');
        }

            $redirectUrl = urldecode($request->redirect_url);

        if($request->add_more == 'true')
        {
            $html = $this->create();

            return Reply::successWithData(__('messages.recordSaved'), ['html' => $html, 'add_more' => true]);
        }

        if ($redirectUrl == '') {
            $redirectUrl = route('leads.index');
        }

        return Reply::successWithData(__('messages.recordSaved'), ['redirectUrl' => $redirectUrl]);

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $this->lead = Lead::with('currency', 'leadAgent', 'leadAgent.user', 'products')->findOrFail($id)->withCustomFields();

        $this->productIds = $this->lead->products->pluck('id')->toArray();

        $this->editPermission = user()->permission('edit_lead');

        abort_403(!($this->editPermission == 'all'
            || ($this->editPermission == 'added' && $this->lead->added_by == user()->id)
            || ($this->editPermission == 'owned' && !is_null( $this->lead->agent_id) && user()->id == $this->lead->leadAgent->user->id)
            || ($this->editPermission == 'both' && ((!is_null( $this->lead->agent_id) && user()->id == $this->lead->leadAgent->user->id)
            || user()->id == $this->lead->added_by)
        )));

        $this->leadAgents = LeadAgent::with('user')->whereHas('user', function ($q) {
            $q->where('status', 'active');
        })->get();

        if ($this->lead->getCustomFieldGroupsWithFields()) {
            $this->fields = $this->lead->getCustomFieldGroupsWithFields()->fields;
        }

        $this->products = Product::all();
        $this->sources = LeadSource::all();
        $this->status = LeadStatus::all();
        $this->categories = LeadCategory::all();
        $this->countries = countries();
        $this->pageTitle = __('modules.lead.updateTitle');
        $this->salutations = Salutation::cases();

        if (request()->ajax()) {
            $html = view('leads.ajax.edit', $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'leads.ajax.edit';
        return view('leads.create', $this->data);

    }

    /**
     * @param UpdateRequest $request
     * @param int $id
     * @return array|void
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    public function update(UpdateRequest $request, $id)
    {
        $lead = Lead::with('leadAgent', 'leadAgent.user')->findOrFail($id);
        $this->editPermission = user()->permission('edit_lead');

        abort_403(!($this->editPermission == 'all'
            || ($this->editPermission == 'added' && $lead->added_by == user()->id)
            || ($this->editPermission == 'owned' && !is_null( $lead->agent_id) && user()->id == $lead->leadAgent->user->id)
            || ($this->editPermission == 'both' && ((!is_null($lead->agent_id) && user()->id == $lead->leadAgent->user->id)
            || user()->id == $lead->added_by)
        )));

        if($request->has('agent_id')) {
            $lead->agent_id = $request->agent_id;
        }

        $lead->company_name = $request->company_name;
        $lead->website = $request->website;
        $lead->address = $request->address;
        $lead->salutation = $request->salutation;
        $lead->client_name = $request->client_name;
        $lead->client_email = $request->client_email;
        $lead->mobile = $request->mobile;
        $lead->source_id = $request->source_id;
        $lead->next_follow_up = $request->next_follow_up;
        $lead->status_id = $request->status;
        $lead->category_id = $request->category_id;
        $lead->value = $request->value;
        $lead->note = trim_editor($request->note);
        $lead->currency_id = $this->company->currency_id;
        $lead->cell = $request->cell;
        $lead->office = $request->office;
        $lead->city = $request->city;
        $lead->state = $request->state;
        $lead->country = $request->country;
        $lead->postal_code = $request->postal_code;
        $lead->save();

        $lead->products()->sync($request->product_id);

        // To add custom fields data
        if ($request->custom_fields_data) {
            $lead->updateCustomFieldData($request->custom_fields_data);
        }

        return Reply::successWithData(__('messages.updateSuccess'), ['redirectUrl' => route('leads.index')]);

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $lead = Lead::with('leadAgent', 'leadAgent.user')->findOrFail($id);
        $this->deletePermission = user()->permission('delete_lead');

        abort_403(!($this->deletePermission == 'all'
            || ($this->deletePermission == 'added' && $lead->added_by == user()->id)
            || ($this->deletePermission == 'owned' && !is_null( $lead->agent_id) && user()->id == $lead->leadAgent->user->id)
            || ($this->deletePermission == 'both' && ((!is_null($lead->agent_id) && user()->id == $lead->leadAgent->user->id)
            || user()->id == $lead->added_by)
        )));

        Lead::destroy($id);
        return Reply::success(__('messages.deleteSuccess'));

    }

    /**
     * @param CommonRequest $request
     * @return array
     */
    public function changeStatus(CommonRequest $request)
    {
        $lead = Lead::findOrFail($request->leadID);
        $this->editPermission = user()->permission('edit_lead');
        $this->changeLeadStatusPermission = user()->permission('change_lead_status');

        abort_403(!(($this->editPermission == 'all' || ($this->editPermission == 'added' && $lead->added_by == user()->id)) || $this->changeLeadStatusPermission == 'all'));

        $lead->status_id = $request->statusID;
        $lead->save();

        return Reply::success(__('messages.recordSaved'));
    }

    public function applyQuickAction(Request $request)
    {
        switch ($request->action_type) {
        case 'delete':
            $this->deleteRecords($request);
                return Reply::success(__('messages.deleteSuccess'));
        case 'change-status':
            $this->changeBulkStatus($request);
                return Reply::success(__('messages.updateSuccess'));
        case 'change-agent':
            if ($request->agent_id == '') {
                return Reply::error(__('messages.noAgentAdded'));
            }

            $this->changeAgentStatus($request);
                return Reply::success(__('messages.updateSuccess'));
        default:
                return Reply::error(__('messages.selectAction'));
        }
    }

    protected function deleteRecords($request)
    {
        abort_403(user()->permission('delete_lead') != 'all');

        Lead::whereIn('id', explode(',', $request->row_ids))->delete();
    }

    protected function changeBulkStatus($request)
    {
        abort_403(!(user()->permission('edit_lead') == 'all' || user()->permission('change_lead_status') == 'all'));

        Lead::whereIn('id', explode(',', $request->row_ids))->update(['status_id' => $request->status]);
    }

    protected function changeAgentStatus($request)
    {
        abort_403(user()->permission('edit_lead') != 'all');

        $leads = Lead::with('leadAgent')->whereIn('id', explode(',', $request->row_ids))->get();

        foreach ($leads as $key => $lead) {
            $lead->agent_id = $request->agent_id;
            $lead->save();
        }
    }

    /**
     *
     * @param int $leadID
     * @return void
     */
    public function followUpCreate($leadID)
    {
        $this->addPermission = user()->permission('add_lead_follow_up');

        abort_403(!in_array($this->addPermission, ['all', 'added']));

        $this->leadID = $leadID;
        $this->lead = Lead::findOrFail($leadID);

        return view('leads.followup.create', $this->data);

    }

    public function leadFollowup()
    {
        $tab = request('tab');
        $this->activeTab = $tab ?: 'overview';
        $this->view = 'leads.ajax.follow-up';
        $dataTable = new LeadFollowupDataTable();

        return $dataTable->render('leads.show', $this->data);
    }

    /**
     * @param FollowUpStoreRequest $request
     * @return array|void
     * @throws \Froiden\RestAPI\Exceptions\RelatedResourceNotFoundException
     */
    public function followUpStore(FollowUpStoreRequest $request)
    {

        $this->lead = Lead::findOrFail($request->lead_id);

        $this->addPermission = user()->permission('add_lead_follow_up');

        abort_403(!in_array($this->addPermission, ['all', 'added']));

        if ($this->lead->next_follow_up != 'yes') {
            return Reply::error(__('messages.leadFollowUpRestricted'));
        }

        $followUp = new LeadFollowUp();
        $followUp->lead_id = $request->lead_id;

        $followUp->next_follow_up_date = Carbon::createFromFormat($this->company->date_format . ' ' . $this->company->time_format, $request->next_follow_up_date . ' ' . $request->start_time)->format('Y-m-d H:i:s');

        $followUp->remark = $request->remark;

        $followUp->send_reminder = $request->send_reminder;
        $followUp->remind_time = $request->remind_time;
        $followUp->remind_type = $request->remind_type;
        $followUp->status = 'incomplete';

        $followUp->save();

        return Reply::success(__('messages.recordSaved'));

    }

    public function editFollow($id)
    {
        $this->follow = LeadFollowUp::findOrFail($id);
        $this->editPermission = user()->permission('edit_lead_follow_up');
        abort_403(!($this->editPermission == 'all' || ($this->editPermission == 'added' && $this->follow->added_by == user()->id)));

        return view('leads.followup.edit', $this->data);
    }

    public function updateFollow(FollowUpStoreRequest $request)
    {
        $this->lead = Lead::findOrFail($request->lead_id);

        $followUp = LeadFollowUp::findOrFail($request->id);
        $this->editPermission = user()->permission('edit_lead_follow_up');

        abort_403(!($this->editPermission == 'all'
        || ($this->editPermission == 'added' && $followUp->added_by == user()->id)
        ));

        if ($this->lead->next_follow_up != 'yes') {
            return Reply::error(__('messages.leadFollowUpRestricted'));
        }


        $followUp->lead_id = $request->lead_id;

        $followUp->next_follow_up_date = Carbon::createFromFormat($this->company->date_format . ' ' . $this->company->time_format, $request->next_follow_up_date . ' ' . $request->start_time)->format('Y-m-d H:i:s');

        $followUp->remark = $request->remark;
        $followUp->send_reminder = $request->send_reminder;
        $followUp->status = $request->status;
        $followUp->remind_time = $request->remind_time;
        $followUp->remind_type = $request->remind_type;

        $followUp->save();

        return Reply::success(__('messages.updateSuccess'));

    }

    public function deleteFollow($id)
    {
        $followUp = LeadFollowUp::findOrFail($id);
        $this->deletePermission = user()->permission('delete_lead_follow_up');
        abort_403(!($this->deletePermission == 'all' || ($this->deletePermission == 'added' && $followUp->added_by == user()->id)));

        LeadFollowUp::destroy($id);

        return Reply::success(__('messages.deleteSuccess'));
    }

    public function proposals()
    {
        $viewPermission = user()->permission('view_lead_proposals');

        abort_403(!in_array($viewPermission, ['all', 'added']));

        $tab = request('tab');
        $this->activeTab = $tab ?: 'overview';
        $this->view = 'leads.ajax.proposal';
        $dataTable = new ProposalDataTable();

        return $dataTable->render('leads.show', $this->data);
    }

    public function gdpr()
    {
        $dataTable = new LeadGDPRDataTable();
        $tab = request('tab');
        $this->activeTab = $tab ?: 'gdpr';
        $this->view = 'leads.ajax.gdpr';
        return $dataTable->render('leads.show', $this->data);
    }

    public function consent(Request $request)
    {
        $leadId = $request->leadId;
        $this->consentId = $request->consentId;
        $this->leadId = $leadId;

        $this->consent = PurposeConsent::with(['lead' => function ($query) use ($request) {
            $query->where('lead_id', $request->leadId)
                ->orderBy('created_at', 'desc');
        }])
            ->where('id', $request->consentId)
            ->first();

        return view('leads.gdpr.consent-form', $this->data);
    }

    public function saveLeadConsent(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);
        $consent = PurposeConsent::findOrFail($request->consent_id);

        if ($request->consent_description && $request->consent_description != '') {
            $consent->description = trim_editor($request->consent_description);
            $consent->save();
        }

        // Saving Consent Data
        $newConsentLead = new PurposeConsentLead();
        $newConsentLead->lead_id = $lead->id;
        $newConsentLead->purpose_consent_id = $consent->id;
        $newConsentLead->status = trim($request->status);
        $newConsentLead->ip = $request->ip();
        $newConsentLead->updated_by_id = $this->user->id;
        $newConsentLead->additional_description = $request->additional_description;
        $newConsentLead->save();

        return $request->status == 'agree' ? Reply::success(__('messages.consentOptIn')) : Reply::success(__('messages.consentOptOut'));
    }

    public function importLead()
    {
        $this->pageTitle = __('app.importExcel') . ' ' . __('app.menu.lead');

        $this->addPermission = user()->permission('add_lead');
        abort_403(!in_array($this->addPermission, ['all', 'added']));

        if (request()->ajax()) {
            $html = view('leads.ajax.import', $this->data)->render();
            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle]);
        }

        $this->view = 'leads.ajax.import';

        return view('leads.create', $this->data);
    }

    public function importStore(ImportRequest $request)
    {
        $this->importFileProcess($request, LeadImport::class);

        $view = view('leads.ajax.import_progress', $this->data)->render();

        return Reply::successWithData(__('messages.importUploadSuccess'), ['view' => $view]);
    }

    public function importProcess(ImportProcessRequest $request)
    {
        $batch = $this->importJobProcess($request, LeadImport::class, ImportLeadJob::class);

        return Reply::successWithData(__('messages.importProcessStart'), ['batch' => $batch]);
    }

    public function notes()
    {
        $dataTable = new LeadNotesDataTable();
        $viewPermission = user()->permission('view_lead');

        abort_403 (!($viewPermission == 'all' || $viewPermission == 'added' || $viewPermission == 'both'));

        $tab = request('tab');
        $this->activeTab = $tab ?: 'profile';

        $this->view = 'leads.ajax.notes';

        return $dataTable->render('leads.show', $this->data);
    }

    public function changeFollowUpStatus(Request $request)
    {
        $id = $request->id;
        $status = $request->status;
        $leadFollowUp = LeadFollowUp::find($id);

        if(!is_null($leadFollowUp)){
            $leadFollowUp->status = $status;
            $leadFollowUp->save();
        }

        return Reply::success(__('messages.leadStatusChangeSuccess'));

    }

}
