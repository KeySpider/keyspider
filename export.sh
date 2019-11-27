#!/bin/bash 
source .env
workDir=/Users/tuanleanh/PhpstormProjects/keyspider
exportConfigDir=$workDir/storage/ini_configs/extract
exportSourceConfigDir=$workDir/storage/tests/ini_configs/extract
logFolder=$workDir/storage/logs

make_folders() {
    mkdir -p $exportConfigDir

    mkdir -p $workDir/storage/delivery_csv_processed/organization/
    mkdir -p $workDir/storage/delivery_csv_processed/user/
    mkdir -p $workDir/storage/delivery_csv_processed/role/

    mkdir -p $workDir/storage/extract_csv_temp/organization/
    mkdir -p $workDir/storage/extract_csv_temp/role/
    mkdir -p $workDir/storage/extract_csv_temp/user/
}

start_export() {
    orgCfg1="OrganizationInfoExtraction4CSV.ini"
    roleCfg1="RoleInfoExtraction4CSV.ini"
    userCfg1="UserInfoExtraction4CSV.ini"

    orgCfg2="OrganizationInfoOutput4CSV.ini"
    roleCfg2="RoleInfoOutput4CSV.ini"    
    userCfg2="UserInfoOutput4CSV.ini"

    cp -f $exportSourceConfigDir/$orgCfg1 $exportConfigDir/$orgCfg1
    cp -f $exportSourceConfigDir/$roleCfg1 $exportConfigDir/$roleCfg1
    cp -f $exportSourceConfigDir/$userCfg1 $exportConfigDir/$userCfg1

    cp -f $exportSourceConfigDir/$orgCfg2 $exportConfigDir/$orgCfg2
    cp -f $exportSourceConfigDir/$roleCfg2 $exportConfigDir/$roleCfg2
    cp -f $exportSourceConfigDir/$userCfg2 $exportConfigDir/$userCfg2

    min1=1
    min2=2
    extractStart=`date -d "today + $min1 minutes" +'%H\:%M'`
    outputStart=`date -d "today + $min2 minutes" +'%H\:%M'`

    orgCron="ExecutionTime\[\] = $extractStart"
    roleCron="ExecutionTime\[\] = $extractStart"
    userCron="ExecutionTime\[\] = $extractStart"
    cronText="ExecutionTime\[\] = 00\:00"

    sed -i "s|$cronText|$orgCron|g" "$exportConfigDir/$orgCfg1"
    sed -i "s|$cronText|$roleCron|g" "$exportConfigDir/$roleCfg1"
    sed -i "s|$cronText|$userCron|g" "$exportConfigDir/$userCfg1"

    orgCron="ExecutionTime\[\] = $outputStart"
    roleCron="ExecutionTime\[\] = $outputStart"
    userCron="ExecutionTime\[\] = $outputStart"
    cronText="ExecutionTime\[\] = 00\:00"

    sed -i "s|$cronText|$orgCron|g" "$exportConfigDir/$orgCfg2"
    sed -i "s|$cronText|$roleCron|g" "$exportConfigDir/$roleCfg2"
    sed -i "s|$cronText|$userCron|g" "$exportConfigDir/$userCfg2"
}

view_export_log() {
    logFile=`date -d "today" +'%Y-%m-%d'`
    dialog --textbox $logFolder/export-$logFile.log 25 75
}


show_menus() {
    HEIGHT=20
    WIDTH=60
    CHOICE_HEIGHT=10
    BACKTITLE="LDAP-ID"
    TITLE="Export Test Suite"
    MENU="Choose one of the following options"
    OPTIONS=(
             0 "Exit"
             1 "Export data"
             2 "View export log"
             )
    CHOICE=$(dialog --clear \
                    --backtitle "$BACKTITLE" \
                    --title "$TITLE" \
                    --menu "$MENU" \
                    $HEIGHT $WIDTH $CHOICE_HEIGHT \
                    "${OPTIONS[@]}" \
                    2>&1 >/dev/tty)

    clear
    case $CHOICE in
            0) 
                exit 0
                ;;
            1)
                start_export
                ;;
            2)
                view_export_log
                ;;
    esac
}  

# -----------------------------------
Main Logic - Infinite Loop
# ------------------------------------
cd $workDir
make_folders

while true
do 
	show_menus
done    
