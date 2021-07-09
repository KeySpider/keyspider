<?php
namespace App\Ldaplibs\Report;

use App\Commons\Consts;
use Illuminate\Support\Facades\DB;

class DetailReport
{

    private $info;
    private $reportId;

    private const TABLE_NAME = "DetailReport";

    public function __construct()
    {
        $this->info = array(
            "keyspiderId"       => "",
            "externalId"        => "",
            "crudType"          => "",
            "dataDetail"        => "",
            "processedDatetime" => "",
        );
    }

    public function setReportId($reportId)
    {
        $this->reportId = $reportId;
    }

    public function clear()
    {
        $this->info = array(
            "keyspiderId"       => "",
            "externalId"        => "",
            "crudType"          => "",
            "dataDetail"        => "",
            "processedDatetime" => date(Consts::DATE_FORMAT_YMDHIS),
        );
    }

    public function setKeyspiderId($keyspiderId)
    {
        $this->info["keyspiderId"] = $keyspiderId;
    }

    public function setExternalId($externalId)
    {
        $this->info["externalId"] = $externalId;
    }

    public function setCrudType($crudType)
    {
        $this->info["crudType"] = $crudType;
    }

    public function setDataDetail($dataDetail)
    {
        $this->info["dataDetail"] = $dataDetail;
    }

    public function setProcessedDatetime($processedDatetime = null)
    {
        if ($processedDatetime == null) $processedDatetime = date(Consts::DATE_FORMAT_YMDHIS);
        $this->info["processedDatetime"] = $processedDatetime;
    }

    public function create($status = "success")
    {
        $report = array(
            "ID"                => $this->reportId,
            "KeyspiderID"       => $this->info["keyspiderId"],
            "ExternalID"        => $this->info["externalId"],
            "CrudType"          => $this->info["crudType"],
            "Status"            => $status,
            "DataDetail"        => $this->info["dataDetail"],
            "ProcessedDatetime" => $this->info["processedDatetime"],
        );

        DB::beginTransaction();
        DB::table(self::TABLE_NAME)->insert($report);
        DB::commit();
    }
}
