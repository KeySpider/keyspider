<?php

namespace App\Ldaplibs\SCIM\GoogleWorkspace;

use Google_Service_Directory;
use Google_Service_Directory_User;
use Google_Service_Directory_UserName;

class User
{
    private $service;
    private $user;

    public function __construct($client, $user = null)
    {
        $this->service = new Google_Service_Directory($client);
        $this->user = new Google_Service_Directory_User();
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getPrimaryKey($user)
    {
        return $user['id'];
    }

    public function setResource($user)
    {
        return $this->user = $user;
    }

    public function insert()
    {
        return $this->service->users->insert($this->user);
    }

    public function update($id)
    {
        return $this->service->users->update($id, $this->user);
    }

    public function delete($id)
    {
        return $this->service->users->delete($id);
    }

    public function get($id)
    {
        return $this->service->users->get($id);
    }

    public function setAttributes($values)
    {
        foreach ($values as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function setAttribute($key, $value)
    {
        switch (true) {
            case $key == 'addresses':
                $this->setAddresses($value);
                break;
            case strpos($key, 'addresses.') !== false:
                $type = explode('.', $key)[1];
                $this->setAddressesAttribute($type, $value);
                break;
            case $key == 'archived':
                $this->setArchived($value);
                break;
            case $key == 'changePasswordAtNextLogin':
                $this->setChangePasswordAtNextLogin($value);
                break;
            case $key == 'customSchemas':
                $this->setCustomSchemas($value);
                break;
            case $key == 'emails':
                $this->setEmails($value);
                break;
            case strpos($key, 'emails.') !== false:
                $type = explode('.', $key)[1];
                $this->setEmailsAttribute($type, $value);
                break;
            case $key == 'externalIds':
                $this->setExternalIds($value);
                break;
            case strpos($key, 'externalIds.') !== false:
                $type = explode('.', $key)[1];
                $this->setExternalIdsAttribute($type, $value);
                break;
            case $key == 'familyName':
                $this->setName($key, $value);
                break;
            case $key == 'fullName':
                $this->setName($key, $value);
                break;
            case $key == 'gender':
                $this->setGender($value);
                break;
            case strpos($key, 'gender.') !== false:
                $type = explode('.', $key)[1];
                $this->setGenderAttribute($type, $value);
                break;
            case $key == 'givenName':
                $this->setName($key, $value);
                break;
            case $key == 'hashFunction':
                $this->setHashFunction($value);
                break;
            case $key == 'id':
                $this->setId($value);
                break;
            case $key == 'ims':
                $this->setIms($value);
                break;
            case strpos($key, 'ims.') !== false:
                $type = explode('.', $key)[1];
                $this->setImsAttribute($type, $value);
                break;
            case $key == 'includeInGlobalAddressList':
                $this->setIncludeInGlobalAddressList($value);
                break;
            case $key == 'ipWhitelisted':
                $this->setIpWhitelisted($value);
                break;
            case $key == 'keywords':
                $this->setKeywords($value);
                break;
            case strpos($key, 'keywords.') !== false:
                $type = explode('.', $key)[1];
                $this->setKeywordsAttribute($type, $value);
                break;
            case $key == 'languages':
                $this->setLanguages($value);
                break;
            case strpos($key, 'languages.') !== false:
                $type = explode('.', $key)[1];
                $this->setLanguagesAttribute($type, $value);
                break;
            case $key == 'locations':
                $this->setLocations($value);
                break;
            case strpos($key, 'locations.') !== false:
                $type = explode('.', $key)[1];
                $this->setLocationsAttribute($type, $value);
                break;
            case strpos($key, 'name.') !== false:
                $type = explode('.', $key)[1];
                $this->setName($type, $value);
                break;
            case $key == 'notes':
                $this->setNotes($value);
                break;
            case $key == 'orgUnitPath':
                $this->setOrgUnitPath($value);
                break;
            case $key == 'organizations':
                $this->setOrganizations($value);
                break;
            case strpos($key, 'organizations.') !== false:
                $type = explode('.', $key)[1];
                $this->setOrganizationsAttribute($type, $value);
                break;
            case $key == 'password':
                $this->setPassword($value);
                break;
            case $key == 'phones':
                $this->setPhones($value);
                break;
            case strpos($key, 'phones.') !== false:
                $type = explode('.', $key)[1];
                $this->setPhonesAttribute($type, $value);
                break;
            case $key == 'posixAccounts':
                $this->setPosixAccounts($value);
                break;
            case strpos($key, 'posixAccounts.') !== false:
                $type = explode('.', $key)[1];
                $this->setPosixAccountsAttribute($type, $value);
                break;
            case $key == 'primaryEmail':
                $this->setPrimaryEmail($value);
                break;
            case $key == 'recoveryEmail':
                $this->setRecoveryEmail($value);
                break;
            case $key == 'recoveryPhone':
                $this->setRecoveryPhone($value);
                break;
            case $key == 'relations':
                $this->setRelations($value);
                break;
            case strpos($key, 'relations.') !== false:
                $type = explode('.', $key)[1];
                $this->setRelationsAttribute($type, $value);
                break;
            case $key == 'sshPublicKeys':
                $this->setSshPublicKeys($value);
                break;
            case strpos($key, 'sshPublicKeys.') !== false:
                $type = explode('.', $key)[1];
                $this->setSshPublicKeysAttribute($type, $value);
                break;
            case $key == 'suspended':
                $this->setSuspended($value);
                break;
            case $key == 'websites':
                $this->setWebsites($value);
                break;
            case strpos($key, 'websites.') !== false:
                $type = explode('.', $key)[1];
                $this->setWebsitesAttribute($type, $value);
                break;
        }
    }

    private function setAddresses($value)
    {
        $this->setAddressesAttribute('value', $value);
    }

    private function setAddressesAttribute($type, $value)
    {
        $addresses = $this->user->getAddresses();
        if ($addresses == null || count($addresses) == 0) {
            $address = array(
                $type => $value,
                'primary' => true,
            );
            $addresses = array($address);
        } else {
            foreach ($addresses as $index => $address) {
                if (array_key_exists('primary', $address) && $address['primary'] == true) {
                    $addresses[$index][$type] = $value;
                }
            }
        }
        $this->user->setAddresses($addresses);
    }

    private function setArchived($value)
    {
        $this->user->setArchived($value);
    }

    private function setChangePasswordAtNextLogin($value)
    {
        $this->user->setChangePasswordAtNextLogin($value);
    }

    private function setCustomSchemas($value)
    {
        $this->user->setCustomSchemas($value);
    }

    private function setEmails($value)
    {
        $this->setEmailsAttribute('address', $value);
    }

    private function setEmailsAttribute($type, $value)
    {
        $emails = $this->user->getEmails();
        if ($emails == null || count($emails) == 0) {
            $email = array(
                $type => $value,
                'primary' => true,
            );
            $emails = array($email);
        } else {
            foreach ($emails as $index => $email) {
                if (array_key_exists('primary', $email) && $email['primary'] == true) {
                    $emails[$index][$type] = $value;
                }
            }
        }
        $this->user->setEmails($emails);
    }

    private function setExternalIds($value)
    {
        $this->setExternalIdsAttribute('value', $value);
    }

    private function setExternalIdsAttribute($type, $value)
    {
        $externalIds = $this->user->getExternalIds();
        if ($externalIds == null || count($externalIds) == 0) {
            $externalId = array(
                $type => $value,
            );
            $externalIds = array($externalId);
        } else {
            foreach ($externalIds as $index => $externalId) {
                $externalIds[$index][$type] = $value;
            }
        }
        $this->user->setExternalIds($externalIds);
    }

    private function setFamilyName($value)
    {
        $name = $this->user->getName();
        if ($name == null) $name = new Google_Service_Directory_UserName();
        $name->setFamilyName($value);
        $this->user->setName($name);
    }

    private function setFullName($value)
    {
        $name = $this->user->getName();
        if ($name == null) $name = new Google_Service_Directory_UserName();
        $name->setFullName($value);
        $this->user->setName($name);
    }

    private function setGender($value)
    {
        $this->user->setGenderAttribute('addressMeAs', $value);
    }

    private function setGenderAttribute($type, $value)
    {
        $genders = $this->user->getGender();
        if ($genders == null || count($genders) == 0) {
            $gender = array(
                $type => $value,
            );
            $genders = array($gender);
        } else {
            foreach ($genders as $index => $gender) {
                $genders[$index][$type] = $value;
            }
        }
        $this->user->setGender($genders);
    }

    private function setGivenName($value)
    {
        $name = $this->user->getName();
        if ($name == null) $name = new Google_Service_Directory_UserName();
        $name->setGivenName($value);
        $this->user->setName($name);
    }

    private function setHashFunction($value)
    {
        $this->user->setHashFunction($value);
    }

    private function setId($value)
    {
        $this->user->setId($value);
    }

    private function setIms($value)
    {
        $this->setImsAttribute('im', $value);
    }

    private function setImsAttribute($type, $value)
    {
        $ims = $this->user->getIms();
        if ($ims == null || count($ims) == 0) {
            $im = array(
                $type => $value,
                'primary' => true,
            );
            $ims = array($im);
        } else {
            foreach ($ims as $index => $im) {
                if (array_key_exists('primary', $im) && $im['primary'] == true) {
                    $ims[$index][$type] = $value;
                }
            }
        }
        $this->user->setIms($ims);
    }

    private function setIncludeInGlobalAddressList($value)
    {
        $this->user->setIncludeInGlobalAddressList($value);
    }

    private function setIpWhitelisted($value)
    {
        $this->user->setIpWhitelisted($value);
    }

    private function setKeywords($value)
    {
        $this->setKeywordsAttribute('value', $value);
    }

    private function setKeywordsAttribute($type, $value)
    {
        $keywords = $this->user->getKeywords();
        if ($keywords == null || count($keywords) == 0) {
            $keyword = array(
                $type => $value,
            );
            $keywords = array($keyword);
        } else {
            foreach ($keywords as $index => $keyword) {
                $keywords[$index][$type] = $value;
            }
        }
        $this->user->setKeywords($keywords);
    }

    private function setLanguages($value)
    {
        $this->setLanguagesAttribute('languageCode', $value);
    }

    private function setLanguagesAttribute($type, $value)
    {
        $languages = $this->user->getLanguages();
        if ($languages == null || count($languages) == 0) {
            $language = array(
                $type => $value,
            );
            $languages = array($language);
        } else {
            foreach ($languages as $index => $language) {
                $languages[$index][$type] = $value;
            }
        }
        $this->user->setLanguages($languages);
    }

    private function setLocations($value)
    {
        $this->setLocationsAttribute('area', $value);
    }

    private function setLocationsAttribute($type, $value)
    {
        $locations = $this->user->getLocations();
        if ($locations == null || count($locations) == 0) {
            $location = array(
                $type => $value,
            );
            $locations = array($location);
        } else {
            foreach ($locations as $index => $location) {
                $locations[$index][$type] = $value;
            }
        }
        $this->user->setLocations($locations);
    }

    private function setName($type, $value)
    {
        $name = $this->user->getName();
        if ($name == null) $name = new Google_Service_Directory_UserName();
        if ($type == 'givenName') $name->setGivenName($value);
        if ($type == 'familyName') $name->setFamilyName($value);
        if ($type == 'fullName') $name->setFullName($value);
        $this->user->setName($name);
    }

    private function setNotes($value)
    {
        $this->user->setNotes($value);
    }

    private function setOrgUnitPath($value)
    {
        if (empty($value)) {
            $value = '/';
        }
        if (substr($value, 0, 1) != '/') {
            $value = '/' . $value;
        }
        $this->user->setOrgUnitPath($value);
    }

    private function setOrganizations($value)
    {
        $this->setOrganizationsAttribute('name', $value);
    }

    private function setOrganizationsAttribute($type, $value)
    {
        $organizations = $this->user->getOrganizations();
        if ($organizations == null || count($organizations) == 0) {
            $organization = array(
                $type => $value,
                'primary' => true,
            );
            $organizations = array($organization);
        } else {
            foreach ($organizations as $index => $organization) {
                if (array_key_exists('primary', $organization) && $organization['primary'] == true) {
                    $organizations[$index][$type] = $value;
                }
            }
        }
        $this->user->setOrganizations($organizations);
    }

    private function setPassword($value)
    {
        $this->user->setPassword($value);
    }

    private function setPhones($value)
    {
        $this->setPhonesAttribute('value', $value);
    }

    private function setPhonesAttribute($type, $value)
    {
        $phones = $this->user->getPhones();
        if ($phones == null || count($phones) == 0) {
            $phone = array(
                $type => $value,
                'primary' => true,
            );
            $phones = array($phone);
        } else {
            foreach ($phones as $index => $phone) {
                if (array_key_exists('primary', $phone) && $phone['primary'] == true) {
                    $phones[$index][$type] = $value;
                }
            }
        }
        $this->user->setPhones($phones);
    }

    private function setPosixAccounts($value)
    {
        $this->setPosixAccountsAttribute('accountId', $value);
    }

    private function setPosixAccountsAttribute($type, $value)
    {
        $posixAccounts = $this->user->getPosixAccounts();
        if ($posixAccounts == null || count($posixAccounts) == 0) {
            $posixAccount = array(
                $type => $value,
                'primary' => true,
            );
            $posixAccounts = array($posixAccount);
        } else {
            foreach ($posixAccounts as $index => $posixAccount) {
                if (array_key_exists('primary', $posixAccount) && $posixAccount['primary'] == true) {
                    $posixAccounts[$index][$type] = $value;
                }
            }
        }
        $this->user->setPosixAccounts($posixAccounts);
    }

    private function setPrimaryEmail($value)
    {
        $this->user->setPrimaryEmail($value);
    }

    private function setRecoveryEmail($value)
    {
        $this->user->setRecoveryEmail($value);
    }

    private function setRecoveryPhone($value)
    {
        $this->user->setRecoveryPhone($value);
    }

    private function setRelations($value)
    {
        $this->setRelationsAttribute('value', $value);
    }

    private function setRelationsAttribute($type, $value)
    {
        $relations = $this->user->getRelations();
        if ($relations == null || count($relations) == 0) {
            $relation = array(
                $type => $value,
            );
            $relations = array($relation);
        } else {
            foreach ($relations as $index => $relation) {
                $relations[$index][$type] = $value;
            }
        }
        $this->user->setRelations($relations);
    }

    private function setSshPublicKeys($value)
    {
        $this->setSshPublicKeysAttribute('key', $value);
    }

    private function setSshPublicKeysAttribute($type, $value)
    {
        $sshPublicKeys = $this->user->getSshPublicKeys();
        if ($sshPublicKeys == null || count($sshPublicKeys) == 0) {
            $sshPublicKey = array(
                $type => $value,
            );
            $sshPublicKeys = array($sshPublicKey);
        } else {
            foreach ($sshPublicKeys as $index => $sshPublicKey) {
                $sshPublicKeys[$index][$type] = $value;
            }
        }
        $this->user->setSshPublicKeys($sshPublicKeys);
    }

    private function setSuspended($value)
    {
        $this->user->setSuspended($value);
    }

    private function setWebsites($value)
    {
        $this->setWebsitesAttribute('value', $value);
    }

    private function setWebsitesAttribute($type, $value)
    {
        $websites = $this->user->getWebsites();
        if ($websites == null || count($websites) == 0) {
            $website = array(
                $type => $value,
                'primary' => true,
            );
            $websites = array($website);
        } else {
            foreach ($websites as $index => $website) {
                if (array_key_exists('primary', $website) && $website['primary'] == true) {
                    $websites[$index][$type] = $value;
                }
            }
        }
        $this->user->setWebsites($websites);
    }
}
