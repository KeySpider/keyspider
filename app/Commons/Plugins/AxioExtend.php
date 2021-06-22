<?php

namespace App\Commons\Plugins;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AxioExtend
{
    const MAIL_DOMAIN = "@watchfield.onmicrosoft.com";

    const GROUP_SCOPE_DOMAIN_LOCAL = -2147483644;
    const GROUP_SCOPE_GLOBAL = -2147483646;
    const GROUP_SCOPE_UNIVERSAL = -2147483640;

    const GROUP_TYPE_365 = 2;
    const GROUP_TYPE_SECURITY = self::GROUP_SCOPE_GLOBAL;

    const AD_GROUP_TYPE = array(
        "Microsoft 365" => self::GROUP_TYPE_365,
        "Security" => self::GROUP_TYPE_SECURITY
    );

    const AAD_GROUP_TYPE_365 = "Microsoft 365";
    const AAD_GROUP_TYPE_SECURITY ="Security";

    const AAD_GROUP_SCOPE_PRIVATE = "Private";
    const AAD_GROUP_SCOPE_PUBLIC = "Public";



    /*
     * @params array(FIRSTNAME_LOCAL, LASTNAME_LOCAL)
     * @return LASTNAME_LOCAL ＋ " " ＋ FIRSTNAME_LOCAL
     */
    public function generateDisplayName($data)
    {
        return sprintf("%s %s", $data[1], $data[0]);
    }

    /*
     * @params array(FIRSTNAME_EN, LASTNAME_EN, EMPLOYEE_NUM)
     * @return string(FIRSTNAME_EN の先頭1文字 ＋ "." + LASTNAME_EN 
     *                ＋ EMPLOYEE_NUM の下 3 桁 ＋"@axiozero.work")
     */
    public function generateEmail($data)
    {
        $singleAlph = substr($data[0], 0, 1);
        $lastEmpNo = substr($data[2], -3);
        return sprintf("%s.%s%s@axiozero.work", $singleAlph, $data[1], $lastEmpNo);
    }

    /*
     * @desc   何が入ってくるか分からないのでラッパー関数
     * @params array(SYSTEM01USE_FL)
     * @return string('0' or '1')
     */
    public function generateRoleFlag($data)
    {
        if ((int)$data[0] == 1) {
            return '1';
        }
        return '0';
    }

    /*
     * @desc   [LEAVE_FL]と[SECONDED_FL]のどちらかが「1」のとき「1」を返却（一時停止）
     * @params array(LEAVE_FL, SECONDED_FL)
     * @return string('0' or '1')
     */
    public function isLocked($data)
    {
        $retValue = '0';
        foreach ($data as $idx => $value) {
            if ((int)$value === 1) {
                $retValue = '1';
                break;
            }
        }
        return $retValue;
    }

    /*
     * @desc   [DEL_FL]が「1」のとき「1」（無効化）
     * @params array(LEAVE_FL, LEAVE_FL)
     * @return string('0' or '1')
     */
    public function isDeleted($data)
    {
        $retValue = '0';
        if ((int)$data[0] === 1) $retValue = '1';
        return $retValue;
    }

    /*
     * @desc   上位組織名称まで再帰的に取得する
     * @params array(UpperID,Name)
     * @return string(親組織 子組織 孫組織)
     */
    public function concatOfAncestor($data)
    {
        $nameArray = [];
        $upperID = $data[0];

        while (!empty($upperID)) {
            list($upperID, $orgName) = $this->getOrganizationInfo($upperID);
            $nameArray[] = $orgName;
        }
        $description = implode(" ", array_reverse($nameArray));
        return $description;
    }

    private function getOrganizationInfo($upperID)
    {
        $retID = null;
        $retName = "";

        $parentOrg = DB::table('Organization')->where('ID', $upperID)->first();
        if (!is_null($parentOrg)) {
            $retID = $parentOrg->UpperID;
            $retName = $parentOrg->Name;
        }
        return [$retID, $retName];
    }

    public function generateMailNickName($data)
    {
        preg_match("/^(.*)@(.*)$/", $data[0], $matches);
        if (!empty($matches)) {
            return $matches[1];
        }
        return null;
    }

    public function generateUPN($data)
    {
        $localPart = $this->generateMailNickName((array)$data[0]);
        if (!empty($localPart)) {
            return sprintf("%s%s", $localPart, self::MAIL_DOMAIN);
        }
    }

    public function removeLicenses($data)
    {
        return (int)$data[0] === 1 ? True : False ;
    }

    /*
     * @desc   先頭２桁目が「A」であれば「セキュリティグループ」、「B」であれば「配布グループ」
     *         先頭３桁目が「1」ならグローバル、「2」ならドメインローカル
     * @return integer(groupType decimal value)
     */
    public function generateGroupType($data)
    {
        $retGrouptype = self::GROUP_TYPE_365;
        switch (substr($data[0], 1, 1)) {
        case 'A':
            $retGrouptype = self::GROUP_TYPE_SECURITY;
            switch ((int)substr($data[0], 2, 1)) {
            case 1:
                $retGrouptype = self::GROUP_SCOPE_GLOBAL;
                break;
            case 2:
                $retGrouptype = self::GROUP_SCOPE_DOMAIN_LOCAL;
                break;
            }                
            break;
        case 'B':
            $retGrouptype = self::GROUP_TYPE_365;
            break;
        }
        return $retGrouptype;
    }

    public function generateAADGroupType($data)
    {
        $retAADGroupType = self::AAD_GROUP_TYPE_365;
        if (substr($data[0], 1, 1) == 'B') {
            $retAADGroupType = self::AAD_GROUP_TYPE_SECURITY;
        }
        return $retAADGroupType;
    }

    public function generateAADVisibility($data)
    {
        $retAADGroupScope = null;
        if (substr($data[0], 1, 1) == 'A') {
            switch (substr($data[0], 2, 1)) {
            case '1':
                $retAADGroupScope = self::AAD_GROUP_SCOPE_PUBLIC;
                break;
            case '2':
                $retAADGroupScope = self::AAD_GROUP_SCOPE_PRIVATE;
                break;
            }
        }
        return $retAADGroupScope;
    }

    public function isTeams($data)
    {
        Log::debug('>>> $data[0] >>>>>>');
        Log::debug($data[0]);

        $resorceOption = null;
        if (substr($data[0], 3, 1) == '1') {
            $resorceOption = "Teams";
        }
        return $resorceOption;
    }
}
