<?php
namespace App\Ldaplibs\Report;

use App\Commons\Consts;
use App\Commons\Creator;
use Illuminate\Support\Facades\DB;

class SummaryReport
{

    private $reportId;
    private $startDatetime;

    private const TABLE_NAME = "SummaryReport";

    public function __construct($startDatetime)
    {
        $this->startDatetime = $startDatetime;
    }

    public function makeReportId() {
        $this->reportId = (new Creator())->makeIdBasedOnMicrotime(self::TABLE_NAME);
        return $this->reportId;
    }

    public function setReportId($reportId)
    {
        $this->reportId = $reportId;
    }

    public function create($operation, $target, $table,
        $total, $create, $update, $delete, $error
    )
    {
        $report = array(
            "ID"        => $this->reportId,
            "Operation" => $operation,
            "Target"    => $target,
            "Table"     => $table,
            "Total"     => $total,
            "Create"    => $create,
            "Update"    => $update,
            "Delete"    => $delete,
            "Error"     => $error,
            "StartDatetime" => $this->startDatetime,
            "EndDatetime" => date(Consts::DATE_FORMAT_YMDHIS),
        );
        DB::beginTransaction();
        DB::table(self::TABLE_NAME)->insert($report);
        DB::commit();
    }

}
