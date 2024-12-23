<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use App\Http\Controllers\AccountBaseController;
use App\Models\User;
use App\Scopes\ActiveScope;
use App\Scopes\CompanyScope;
use App\Traits\ModuleVerify;
use Froiden\Envato\Functions\EnvatoUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Session;
use Macellan\Zip\Zip;
use \Nwidart\Modules\Facades\Module;

class CustomModuleController extends AccountBaseController
{

    use ModuleVerify;

    public function __construct()
    {
        parent::__construct();
        $this->pageTitle = 'app.menu.moduleSettings';
        $this->activeSettingMenu = 'module_settings';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function index()
    {
        $this->type = 'custom';
        $this->updateFilePath = config('froiden_envato.tmp_path');
        $this->allModules = Module::toCollection()->filter(function ($module, $key) {
            return $key !== 'UniversalBundle';
        });

        $this->universalBundle = Module::find('UniversalBundle');

        $this->view = 'custom-modules.ajax.custom';
        $this->activeTab = 'custom';
        $plugins = EnvatoUpdate::plugins();
        $version = [];

        foreach ($plugins as $value) {
            $version[$value['envato_id']] = $value['version'];
        }

        $this->version = $version;

        if (request()->ajax()) {
            $html = view($this->view, $this->data)->render();

            return Reply::dataOnly(['status' => 'success', 'html' => $html, 'title' => $this->pageTitle, 'activeTab' => $this->activeTab]);
        }

        return view('module-settings.index', $this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create()
    {
        $this->pageTitle = 'app.menu.moduleSettingsInstall';
        $this->type = 'custom';
        $this->updateFilePath = config('froiden_envato.tmp_path');

        return view('custom-modules.install', $this->data);
    }

    /**
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function store(Request $request)
    {
        if (!extension_loaded('zip')) {
            return Reply::error('<b>PHP-ZIP</b> extension is missing on your server. Please install the extension.');
        }

        File::put(public_path() . '/install-version.txt', 'complete');

        $filePath = $request->filePath;

        $zip = Zip::open($filePath);

        $zipName = $this->getZipName($filePath);

        // Extract the files to storage folder first for checking the right plugin
        // Filename Like codecanyon-0gOuGKoY-zoom-meeting-module-for-worksuite.zip
        if (str_contains($zipName, 'codecanyon-')) {
            $zipName = $this->unzipCodecanyon($zip);
        }
        else {
            $zip->extract(storage_path('app') . '/Modules');
        }

        $moduleName = str_replace('.zip', '', $zipName);


        $validateModule = $this->validateModule($moduleName);

        if ($validateModule ['status'] == true) {
            // Move files to Modules if modules belongs to this product
            File::moveDirectory(storage_path('app') . '/Modules/' . $moduleName, base_path() . '/Modules/' . $moduleName, true);

            // Delete Modules Directory after moving files
            File::deleteDirectory(storage_path('app') . '/Modules/');


            // if module is universal bundle module then activate the module
            if ($moduleName == 'UniversalBundle') {
                $module = Module::findOrFail($moduleName);
                $module->enable();
                Artisan::call('module:migrate', array($moduleName, '--force' => true));
            }

            $this->flushData();

            return Reply::success('Installed successfully.');
        }

        return Reply::error($validateModule ['message']);
    }

    public function validateModule($moduleName)
    {
        $appName = str_replace('-new', '', config('froiden_envato.envato_product_name'));
        $wrongMessage = 'The zip that you are trying to install is not compatible with ' . $appName . ' version';


        if (!extension_loaded('zip')) {
            return [
                'status' => false,
                'message' => '<b>PHP-ZIP</b> extension is missing on your server. Please install the extension.'
            ];
        }

        if (!file_exists(storage_path('app') . '/Modules/' . $moduleName . '/Config/config.php')) {
            return [
                'status' => false,
                'message' => $wrongMessage
            ];
        }

        $config = require_once storage_path('app') . '/Modules/' . $moduleName . '/Config/config.php';

        // parent_min_version id is not defined
        if (!isset($config['parent_min_version'])) {
            if (!App::environment('codecanyon')) {
                return [
                    'status' => false,
                    'message' => 'Minimum version of <b>' . $appName . ' main application</b> is not defined in Module.'
                ];

            } else {
                return [
                    'status' => false,
                    'message' => 'Please download and install the latest version of the module.'
                ];
            }
        }


        // Parent envato id is not defined
        if ($config['parent_min_version'] >= File::get('version.txt')) {
            return [
                'status' => false,
                'message' => 'Minimum version of <b>' . $appName . ' main application</b> should be greater than equal to <b>' . $config['parent_min_version'] . '</b> But your application version is <b>' . File::get('version.txt') . '</b>'
            ];
        }

        // Parent envato id is not defined
        if (!isset($config['parent_envato_id'])) {
            return [
                'status' => false,
                'message' => 'You are installing wrong module for this product'
            ];
        }

        // Parent envato id is different from module envato id
        if ($config['parent_envato_id'] !== config('froiden_envato.envato_item_id')) {
            return [
                'status' => false,
                'message' => 'You are installing wrong module for this product'
            ];
        }

        // Parent product name is not defined
        if (!isset($config['parent_product_name'])) {
            return [
                'status' => false,
                'message' => $wrongMessage
            ];
        }

        // Parent envato id is different from module envato id
        if ($config['parent_product_name'] !== config('froiden_envato.envato_product_name')) {
            return [
                'status' => false,
                'message' => $wrongMessage
            ];
        }

        return [
            'status' => true,
            'message' => 'Unzipped successfully'
        ];


    }

    private function flushData()
    {
        Artisan::call('optimize:clear');
        Artisan::call('view:clear');
        $user = auth()->id();
        // clear cache
        cache()->flush();
        // clear session
        session()->flush();
        auth()->logout();
        // login user
        auth()->loginUsingId($user);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function show($id)
    {
        return $this->verifyModulePurchase($id);
    }

    public function update(Request $request, $moduleName)
    {
        $module = Module::findOrFail($moduleName);

        $status = $request->status;

        ($status == 'active') ? $module->enable() : $module->disable();

        // We are registering the module to run the commands
        $module->register();

        $plugins = \Nwidart\Modules\Facades\Module::allEnabled();

        foreach ($plugins as $plugin) {
            Artisan::call('module:migrate', array($plugin, '--force' => true));
        }

        $command = $moduleName . ':activate';

        // We will call the module function php artisan asset:activate, zoom:active , etc
        if (array_has(\Artisan::all(), $command) && ($status == 'active')) {
            Artisan::call($command);
        }

        if (strtolower($moduleName) == 'languagepack' && ($status == 'active')) {
            \session(['languagepack_module_activated' => true]);
        }

        $this->flushData();

        return Reply::redirect(route('custom-modules.index') . '?tab=custom', 'Status Changed. Reloading');
    }

    public function verifyingModulePurchase(Request $request)
    {
        $request->validate([
            'purchase_code' => 'required|max:80',
        ]);

        $module = $request->module;
        $purchaseCode = $request->purchase_code;

        return $this->modulePurchaseVerified($module, $purchaseCode);
    }

    private function unzipCodecanyon($zip)
    {
        $codeCanyonPath = storage_path('app') . '/Modules/Codecanyon';
        $zip->extract($codeCanyonPath);
        $files = File::allfiles($codeCanyonPath);

        foreach ($files as $file) {

            if (strpos($file->getRelativePathname(), '.zip') !== false) {
                $filePath = $file->getRelativePathname();
                $zip = Zip::open($codeCanyonPath . '/' . $filePath);
                $zip->extract(storage_path('app') . '/Modules');

                return $this->getZipName($filePath);
            }
        }

        return false;
    }

    private function getZipName($filePath)
    {
        $array = explode('/', str_replace('\\', '/', $filePath));

        return end($array);
    }

}
