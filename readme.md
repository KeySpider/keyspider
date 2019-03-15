## About KeySpider
KeySpider is a larabel Application for Managing Information such as Users, Email Addresses and Groups and Synchronize with Various Services.

required Laravel Framework 5.7 over.

## Use KeySpider

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
*Setting for KeySpider Extract Config Files.
```
<your-application-dir>/storage/ini_configs/extract/OrganizationInfoExtraction4CSV.ini
<your-application-dir>/storage/ini_configs/extract/RoleInfoExtraction4CSV.ini
<your-application-dir>/storage/ini_configs/extract/UserInfoExtraction4CSV.ini
```  
*Setting for KeySpider Output Config Files.
```
<your-application-dir>/storage/ini_configs/extract/OrganizationInfoOutput4CSV.ini
<your-application-dir>/storage/ini_configs/extract/RoleInfoOutput4CSV.ini
<your-application-dir>/storage/ini_configs/extract/UserInfoOutput4CSV.ini
```  

