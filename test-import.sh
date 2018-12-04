#!/bin/bash 
workdir=/Applications/MAMP/htdocs/LDAP_ABC
sourceFiles=/Applications/MAMP/htdocs/LDAP_ABC/storage/tests
userTestFile=/Applications/MAMP/htdocs/LDAP_ABC/storage/file_csv/user/hogehoge100.csv
roleTestFile=/Applications/MAMP/htdocs/LDAP_ABC/storage/file_csv/role/hogehoge200.csv
organizationTestFile=/Applications/MAMP/htdocs/LDAP_ABC/storage/file_csv/organization/hogehoge300.csv

clean_up() {
    rm -f /Applications/MAMP/htdocs/LDAP_ABC/storage/file_csv/user/*.csv
    rm -f /Applications/MAMP/htdocs/LDAP_ABC/storage/file_csv/role/*.csv
    rm -f /Applications/MAMP/htdocs/LDAP_ABC/storage/file_csv/organization/*.csv
}

start_import() {    
    start=`date +%s`
    php artisan command:ImportCSV
    end=`date +%s`
    runtime=$((end-start))
    dialog --title "Import Result"  --msgbox "The import process took $runtime second(s)" 6 50
}

import_users() {
    local fileName    
    if [[ "$1" == 1 ]] ; then
        fileName='users.500.csv'
    else
        if [[ "$1" == 2 ]] ; then
        fileName='users.5000.csv'
        else
            fileName='users.50000.csv'
        fi
    fi
    #php artisan command:Truncate_User
    clean_up    
    cp -f $sourceFiles/$fileName $userTestFile
    start_import
}

import_roles() {
    local fileName    
    if [[ "$1" == 1 ]] ; then
        fileName='roles.100.csv'
    else
        if [[ "$1" == 2 ]] ; then
            fileName='roles.1000.csv'
        else
            fileName='roles.10000.csv'
        fi
    fi
    #php artisan command:Truncate_Role
    clean_up
    cp -f $sourceFiles/$fileName $roleTestFile
    start_import
}

import_organizations() {
    local fileName    
    if [[ "$1" == 1 ]] ; then
        fileName='organizations.100.csv'
    else
        if [[ "$1" == 2 ]] ; then
        fileName='organizations.1000.csv'
        else
            fileName='organizations.10000.csv'
        fi
    fi
    #php artisan command:Truncate_Organization
    clean_up
    cp -f $sourceFiles/$fileName $organizationTestFile
    start_import
}

show_menus() {
    HEIGHT=20
    WIDTH=40
    CHOICE_HEIGHT=10
    BACKTITLE="LDAP-ID"
    TITLE="Import Test Suite"
    MENU="Choose one of the following options"
    OPTIONS=(
             0 "Exit"
             1 "Import 500 users"
             2 "Import 5000 users"
             3 "Import 50,000 users"
             4 "Import 100 roles"
             5 "Import 1000 roles"
             6 "Import 10,000 roles"
             7 "Import 100 organizations"
             8 "Import 1000 organizations"
             9 "Import 10,000 organizations"
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
            0) exit 0
                ;;
            1)
                import_users 1
                ;;
            2)
                import_users 2
                ;;
            3)
                 import_users 3
                ;;
            4)
                import_roles 1
                ;;
            5)
                import_roles 2
                ;;
            6)
                import_roles 3
                ;;
            7)
                import_organizations 1
                ;;
            8)
                import_organizations 2
                ;;
            9)
                import_organizations 3
                ;;
    esac
}  

# -----------------------------------
Main Logic - Infinite Loop
# ------------------------------------
cd $workdir
while true
do 
	show_menus
done    
