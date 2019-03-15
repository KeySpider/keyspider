## About KeySpider
KeySpider is a larabel Application for Managing Information such as Users, Email Addresses and Groups and Synchronize with Various Services.

required Laravel Framework 5.7 over.

## Setup KeySpider
* Setting for laravel Config Files.
* Add Schecule Job To cron.  
```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```  
* Setting for KeySpider General Config Files.
```
<your-application-dir>/storage/ini_configs/MasterDBConf.ini
<your-application-dir>/storage/ini_configs/KeySpider.ini
<your-application-dir>/storage/ini_configs/QueueSettings.ini
```  
* Setting for KeySpider Import Config Files.
```
<your-application-dir>/storage/ini_configs/import/OrganizationInfoCSVImport.ini
<your-application-dir>/storage/ini_configs/import/RoleInfoCSVImport.ini
<your-application-dir>/storage/ini_configs/import/UserInfoCSVImport.ini
```  
* Setting for KeySpider Extract Config Files.
```
<your-application-dir>/storage/ini_configs/extract/OrganizationInfoExtraction4CSV.ini
<your-application-dir>/storage/ini_configs/extract/RoleInfoExtraction4CSV.ini
<your-application-dir>/storage/ini_configs/extract/UserInfoExtraction4CSV.ini
```  
* Setting for KeySpider Output Config Files.
```
<your-application-dir>/storage/ini_configs/extract/OrganizationInfoOutput4CSV.ini
<your-application-dir>/storage/ini_configs/extract/RoleInfoOutput4CSV.ini
<your-application-dir>/storage/ini_configs/extract/UserInfoOutput4CSV.ini
```  

## Use KeySpider
* Add Import CSV files.
```
<your-application-dir>/storage/import_csv/organization/
<your-application-dir>/storage/import_csv/role/
<your-application-dir>/storage/import_csv/user/
```  
* Create Processed Files in Directory, After Import jobs Run.
```
<your-application-dir>/storage/import_csv_processed/organization/
<your-application-dir>/storage/import_csv_processed/role/
<your-application-dir>/storage/import_csv_processed/user/
```
* Create Delivery Files in Directory, After Output jobs Run.
```
<your-application-dir>/storage/delivery_csv_processed/organization/
<your-application-dir>/storage/delivery_csv_processed/role/
<your-application-dir>/storage/delivery_csv_processed/user/
```

