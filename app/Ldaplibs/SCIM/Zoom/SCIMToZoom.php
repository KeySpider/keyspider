<?php


namespace App\Ldaplibs\SCIM\Zoom;

use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Log;
use MacsiDigital\Zoom\Facades\Zoom;

class SCIMToZoom
{
    protected $setting;

    /**
     * SCIMToZoom constructor.
     * @param $setting
     */
    public function __construct($setting)
    {
        $this->setting = $setting;
    }

    public function createResource($resourceType, $item)
    {

        $tmpl = $this->replaceResource($resourceType, $item);

        Log::info('Zoom Create -> ' . $tmpl['last_name'] . ' ' . $tmpl['first_name']);
        $user = Zoom::user()->create([
            'first_name' => $tmpl['first_name'],
            'last_name' => $tmpl['last_name'],
            'email' => $tmpl['email'],
            'timezone' => 'Asia/Tokyo',
            'verified' => 0,
            'language' => 'jp-JP',
            'status' => 'active'
        ]);
        return $user->id;
    }

    public function updateResource($resourceType, $item)
    {

        $tmpl = $this->replaceResource($resourceType, $item);

        Log::info('Zoom Update -> ' . $tmpl['last_name'] . ' ' . $tmpl['first_name']);
        try {
            $user = Zoom::user()->find($tmpl['email']);
            $ext_id = $user->id;
            $result = $user->update([
                'first_name' => $tmpl['first_name'],
                'last_name' => $tmpl['last_name'],
                // 'email' => $tmpl['email'],
                'phone_country' => 'JP',
                'phone_number' => $tmpl['phone_number'],
                'timezone' => 'Asia/Tokyo',
                'job_title' => $tmpl['job_title'],
                'type' => 1,
                'verified' => 0,
                'language' => 'jp-JP',
                'status' => 'active',
            ]);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(),'Only paid') !== false) {
                // これは正常系なので無視する
            } else {
                Log::error($exception);
            }
        }
        return $ext_id;
    }

    public function deleteResource($resourceType, $item)
    {
        $user = Zoom::user()->find($item['email']);
        Log::info('Zoom Delete -> ' . $item['last_name'] . ' ' . $item['first_name']);
        $user->delete();
    }

    public function replaceResource($resourceType, $item)
    {
        $settingManagement = new SettingsManager();
        $getEncryptedFields = $settingManagement->getEncryptedFields();

        foreach ($item as $key => $value) {
            $twColumn = "ZOOM.$key";

            if (in_array($twColumn, $getEncryptedFields)) {
                $item[$key] = $settingManagement->passwordDecrypt($value);
            }
        }
        return $item;
    }
}