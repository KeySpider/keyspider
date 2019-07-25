<?php


use Illuminate\Support\Facades\Schema;

class TablesBuilder
{
    public function __construct($masterDBConfigPath)
    {
        $this->masterDBConfigPath = $masterDBConfigPath;
    }

    public function readIniFile()
    {
        return parse_ini_file($this->masterDBConfigPath, true, INI_SCANNER_RAW);
    }

    public function buildTables()
    {
        $buildSucess = true;
        $tablesMap = $this->readIniFile();
//        var_dump($tablesMap);
        foreach ($tablesMap as $tableDesc) {
//            var_dump($tableDesc);
            $tableName = null;
            $columns = [];
            foreach ($tableDesc as $key => $value) {
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
        $columnsInString = implode("|",$columns);
//      Create many User.RoleFlag
        $roleMapCount = isset($this->readIniFile()['RoleMap']['RoleID'])?count($this->readIniFile()['RoleMap']['RoleID']):0;
        foreach ($columns as $column) {
            if($column==="RoleFlag"){
                for( $i= 0 ; $i < $roleMapCount ; $i++ ){
                    $columns[] = "$column-$i";
                }
                unset($columns[$column]);
            }
        }
        if (Schema::hasTable($tableName)) {
            echo "- Update table: \e[1;31;47m<<<$tableName>>>: [$columnsInString]\e[0m\n";
            Schema::table($tableName, function ($table) use ($tableName, $columns) {
                foreach ($columns as $column) {

                    if (!Schema::hasColumn($tableName, $column)) {
                        echo "    + add Column: [$column]\n";
                        $table->string($column)->nullable();
                    }
                }
            });

        } else {
            echo "- Build table: \e[1;31;47m<<<$tableName>>>: [$columnsInString]\e[0m\n";
            Schema::create($tableName, function ($table) use ($columns) {
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }
    }
}
