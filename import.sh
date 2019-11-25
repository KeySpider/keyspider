#!/bin/bash 
source .env
workDir=/Users/tuanleanh/PhpstormProjects/keyspider
importConfigDir=$workDir/storage/ini_configs/import
importSourceConfigDir=$workDir/storage/tests/ini_configs/import
dataTestFolder=$workDir/storage/tests
logFolder=$workDir/storage/logs

userTestFile=$workDir/storage/import_csv/user/hogehoge100.csv
roleTestFile=$workDir/storage/import_csv/role/hogehoge200.csv
organizationTestFile=$workDir/storage/import_csv/organization/hogehoge300.csv

tblUser="AAA"
tblOrganization="BBB"
tblRole="CCC"

export PGPASSWORD=$DB_PASSWORD

make_folders() {
    mkdir -p $importConfigDir

    mkdir -p $workDir/storage/import_csv/organization/
    mkdir -p $workDir/storage/import_csv/user/
    mkdir -p $workDir/storage/import_csv/role/

    mkdir -p $workDir/storage/import_csv_processed/organization/
    mkdir -p $workDir/storage/import_csv_processed/role/
    mkdir -p $workDir/storage/import_csv_processed/user/
}

truncate_data() {
    psql -U $DB_USERNAME -d $DB_DATABASE -h $DB_HOST -p $DB_PORT -c 'TRUNCATE public."'"$1"'"'
}

start_import() {    
    truncate_data $tblUser
    truncate_data $tblOrganization
    truncate_data $tblRole

    userCfg="UserInfoCSVImport.ini"
    roleCfg="RoleInfoCSVImport.ini"
    orgCfg="OrganizationInfoCSVImport.ini"

    mkdir 

    cp -f $importSourceConfigDir/$orgCfg $importConfigDir/$orgCfg
    cp -f $importSourceConfigDir/$roleCfg $importConfigDir/$roleCfg
    cp -f $importSourceConfigDir/$userCfg $importConfigDir/$userCfg

    orgMin=1
    roleMin=2
    userMin=3
    orgStart=`date -d "today + $orgMin minutes" +'%H\:%M'`
    roleStart=`date -d "today + $roleMin minutes" +'%H\:%M'`
    userStart=`date -d "today + $userMin minutes" +'%H\:%M'`

    orgCron="ExecutionTime\[\] = $orgStart"
    roleCron="ExecutionTime\[\] = $roleStart"
    userCron="ExecutionTime\[\] = $userStart"
    cronText="ExecutionTime\[\] = 00\:00"

    sed -i "s|$cronText|$orgCron|g" "$importConfigDir/$orgCfg"
    sed -i "s|$cronText|$roleCron|g" "$importConfigDir/$roleCfg"
    sed -i "s|$cronText|$userCron|g" "$importConfigDir/$userCfg"

    msg="The organization import jobs will be started after $orgMin minutes."
    dialog --title 'Information' --msgbox "$msg" 5 70
    msg="The role import job will be started after $roleMin minutes"
    dialog --title 'Information' --msgbox "$msg" 5 70
    msg="The user import job will be started after $userMin minutes"
    dialog --title 'Information' --msgbox "$msg" 5 70
}

import_users() {
    local fileName    
    if [[ "$1" == 1 ]] ; then
        fileName='users.1000.csv'
    else
        if [[ "$1" == 2 ]] ; then
            fileName='users.10000.csv'
        else
            if [[ "$1" == 3 ]] ; then
                fileName='users.100000.csv'
            else
                fileName='users.10.csv'
            fi
        fi
    fi
    rm -f $workDir/storage/file_csv/user/*.csv
    cp -f $dataTestFolder/$fileName $userTestFile   
}

import_roles() {
    local fileName    
    if [[ "$1" == 1 ]] ; then
        fileName='roles.100.csv'
    else
        if [[ "$1" == 2 ]] ; then
            fileName='roles.1000.csv'
        else
            if [[ "$1" == 3 ]] ; then
                fileName='roles.10000.csv'
            else
                fileName='roles.5.csv'
            fi
        fi
    fi 
    rm -f $workDir/storage/file_csv/role/*.csv
    cp -f $dataTestFolder/$fileName $roleTestFile
}

import_organizations() {
    local fileName    
    if [[ "$1" == 1 ]] ; then
        fileName='organizations.10.csv'
    else
        if [[ "$1" == 2 ]] ; then
            fileName='organizations.100.csv'
        else
            if [[ "$1" == 3 ]] ; then
                fileName='organizations.1000.csv'
            else
                fileName='organizations.2.csv'
            fi
        fi
    fi 
    rm -f $workDir/storage/file_csv/organization/*.csv
    cp -f $dataTestFolder/$fileName $organizationTestFile
}

view_import_log() {
    logFile=`date -d "today" +'%Y-%m-%d'`
    dialog --textbox $logFolder/import-$logFile.log 25 75
}


show_menus() {
    HEIGHT=20
    WIDTH=80
    CHOICE_HEIGHT=10
    BACKTITLE="LDAP-ID"
    TITLE="Import Test Suite"
    MENU="Choose one of the following options"
    OPTIONS=(
             0 "Exit"             
             1 "Import small data (organizations: 10,  roles: 100, user: 1K)"
             2 "Import medium data (organizations: 100,  roles: 1K, user: 10K)"
             3 "Import large data (organizations: 1K,  roles: 10K, user: 100K)"
             4 "View import log"
             5 "Import tiny data (organizations: 2,  roles: 5, user: 10)"
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
                import_organizations 1
                import_roles 1
                import_users 1
                start_import
                ;;
            2)
                import_organizations 2
                import_roles 2
                import_users 2
                start_import
                ;;
            3)
                import_organizations 3
                import_roles 3
                import_users 3
                start_import
                ;;
            4)
                view_import_log
                ;;
            5)
                import_organizations 0
                import_roles 0
                import_users 0
                start_import
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
