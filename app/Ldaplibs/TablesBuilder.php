<?php


use App\Ldaplibs\SettingsManager;
use Illuminate\Support\Facades\Schema;

class TablesBuilder
{
    public function __construct(SettingsManager $settingsManager)
    {
        $this->settingsManager = $settingsManager;
    }

    public function readIniFile()
    {
        return parse_ini_file($this->settingsManager->iniMasterDBFile, true, INI_SCANNER_RAW);
    }

    public function buildTables()
    {

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
        }
        $this->settingsManager->getRoleMapInName();

    }

    /**
     * @param $table
     * @param $column
     * @param $defaultUpdateFlagsData : for column 'UpdateFlags'
     */
    private function addColumnTotable($table, $column, $defaultUpdateFlagsData): void
    {
        $updateFlagColumnName = $this->settingsManager->getUpdateFlagsColumnName($table->getTable());
        $deleteFlagColumnName = $this->settingsManager->getDeleteFlagColumnName($table->getTable());
        $roleFlagColumnName = $this->settingsManager->getBasicRoleFlagColumnName();
        $allRoleFlags = [] ;
        if ($column === $updateFlagColumnName) {
            $table->json($column)->default($defaultUpdateFlagsData);
        } elseif ($column === $deleteFlagColumnName) {
            $table->string($column)->default("0");
        } elseif ($column === $roleFlagColumnName) {
            $roleMapCount = isset($this->readIniFile()['RoleMap']['RoleID']) ? count($this->readIniFile()['RoleMap']['RoleID']) : 0;
            for ($i = 0; $i < $roleMapCount; $i++) {
                $table->string("$column-$i")->default("0");
                $allRoleFlags[] = "$column-$i";
            }
            $this->settingsManager->setRoleFlags($allRoleFlags);
        } else {
            $table->string($column)->nullable();
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
        $roleFlagColumnName = $this->settingsManager->getBasicRoleFlagColumnName();
//      Create many User.RoleFlag
//        $this->buildColumnsWithMultiRoleFlag($columns);
        if (Schema::hasTable($tableName)) {
            echo "- Update table: \e[1;31;47m<<<$tableName>>>: [$columnsInString]\e[0m\n";
//            Update table
            Schema::table($tableName, function ($table) use ($tableName, $columns, $defaultUpdateFlagsData, $roleFlagColumnName) {
                foreach ($columns as $column) {
                    if (!Schema::hasColumn($tableName, $column) and $column!==$roleFlagColumnName) {
                        echo "    + add Column: [$column]\n";
                        $this->addColumnTotable($table, $column, $defaultUpdateFlagsData);
                    }
                }
            });
//          Create table
        } else {
            echo "- Build table: \e[1;31;47m<<<$tableName>>>: [$columnsInString]\e[0m\n";
            Schema::create($tableName, function ($table) use ($columns, $defaultUpdateFlagsData) {
                foreach ($columns as $column) {
                    $this->addColumnTotable($table, $column, $defaultUpdateFlagsData);
                }
            });
        }
    }


    /**
     * @return array|false|string
     * Sample return of User table: [{"UserInfoExtraction4CSV":1},{"UserALLExtraction4CSV":1}]
     * Content: Read all extract files defined in KeySpider.ini to get ExtractionProcessID and matched table name.
     */
    private function getDefaultUpdateFlagsJson($tableName)
    {
        $defaultUpdateFlagsData = [];
        $keySpider = $this->settingsManager->keySpider;
        $extractConfig = array_get($keySpider['CSV Extract Process Configration'], 'extract_config', []);
        foreach ($extractConfig as $extractConfigFile) {
            try {
                $extractConfigContent = parse_ini_file($extractConfigFile);
                $extractProcessID = $extractConfigContent['ExtractionProcessID'];
                if ($tableName == $extractConfigContent['ExtractionTable']) {//check if table name is matched.
                    $defaultUpdateFlagsData[$extractProcessID] = 1;
                }
            } catch (Exception $exception) {

            }
        }
        return json_encode($defaultUpdateFlagsData);
    }
}
