<?php


use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Schema;

class TablesBuilder
{
    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
        $this->masterDBConfigPath = $settingsManager->iniMasterDBFile;
        $this->keySpiderIniPath = "/Users/tuanleanh/PhpstormProjects/keyspider/storage/ini_configs/KeySpider.ini";
    }

    public function readIniFile()
    {
        return parse_ini_file($this->masterDBConfigPath, true, INI_SCANNER_RAW);
    }

    public function buildTables()
    {
        $buildSucess = true;
        $tablesMap = $this->settingsManager->masterDBConfigData;
        foreach ($tablesMap as $tableDesc) {
            $tableName = null;
            $columns = [];
            foreach ($tableDesc as $key => $value) {
                if (is_array($value)) continue;
                if (strpos($key, '.') == false and strpos($value, '.') == false) {
                    $tableName = $value;
                } elseif (strpos($value, '.') == true) {
                    $columnName = explode('.', $value)[1];
                    $columns[] = $columnName;
                }
            }

            if ($tableName and count($columns)) {
                $destinationTable = ['table_name' => $tableName, 'columns' => $columns];
                $this->migrateTable($destinationTable);
            }
//            return ($destinationTable);
        }

    }

    /**
     * @param array $destinationTable
     */
    private function migrateTable(array $destinationTable): void
    {

        $tableName = $destinationTable['table_name'];
        $columns = $destinationTable['columns'];
        $defaultUpdateFlagsData = $this->getDefaultUpdateFlagsJson($tableName);
        $columnsInString = implode("|", $columns);
//      Create many User.RoleFlag
        $this->buildColumnsWithMultiRoleFlag($columns);
        if (Schema::hasTable($tableName)) {
            echo "- Update table: \e[1;31;47m<<<$tableName>>>: [$columnsInString]\e[0m\n";
            Schema::table($tableName, function ($table) use ($tableName, $columns, $defaultUpdateFlagsData) {
                foreach ($columns as $column) {
                    if (!Schema::hasColumn($tableName, $column)) {
                        echo "    + add Column: [$column]\n";
                        if ($column === 'UpdateFlags') {
                            echo "set update flags for $column";
                            $table->json($column)->default($defaultUpdateFlagsData);
                        } else {
                            $table->string($column)->nullable();
                        }
                    }
                }
            });

        } else {
            echo "- Build table: \e[1;31;47m<<<$tableName>>>: [$columnsInString]\e[0m\n";
            Schema::create($tableName, function ($table) use ($columns, $defaultUpdateFlagsData) {
                foreach ($columns as $column) {
//                    $table->string($column)->nullable();
                    if ($column === 'UpdateFlags') {
                        echo "set update flags for $column";
                        $table->json($column)->default($defaultUpdateFlagsData);
                    } else {
                        $table->string($column)->nullable();
                    }

                }
            });
        }
    }

    /**
     * @param $columns
     */
    private function buildColumnsWithMultiRoleFlag(&$columns): void
    {
        $roleMapCount = isset($this->readIniFile()['RoleMap']['RoleID']) ? count($this->readIniFile()['RoleMap']['RoleID']) : 0;
        foreach ($columns as $column) {
            if ($column === "RoleFlag") {
                for ($i = 0; $i < $roleMapCount; $i++) {
                    $columns[] = "$column-$i";
                }
                unset($columns[$column]);
            }
        }
    }

    /**
     * @return array|false|string
     */
    private function getDefaultUpdateFlagsJson($tableName)
    {
        $defaultUpdateFlagsData = [];
        $keySpider = parse_ini_file($this->keySpiderIniPath);
        $extractConfig = array_get($keySpider, 'extract_config', []);
        foreach ($extractConfig as $extractConfigFile) {
            try {
                $extractConfigContent = parse_ini_file($extractConfigFile);
                $extractProcessID = $extractConfigContent['ExtractionProcessID'];
                if ($tableName == $extractConfigContent['ExtractionTable']) {
                    $defaultUpdateFlagsData[] = [$extractProcessID => 0];
                }
            } catch (Exception $exception) {

            }
        }
        return json_encode($defaultUpdateFlagsData);
    }
}
