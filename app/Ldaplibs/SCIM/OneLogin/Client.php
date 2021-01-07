<?php

namespace App\Ldaplibs\SCIM\OneLogin;

use OneLogin\api\OneLoginClient;

class Client extends OneLoginClient
{
    const CREATE_ROLE_URL = 'https://api.%s.onelogin.com/api/2/roles';
    const UPDATE_ROLE_URL = 'https://api.%s.onelogin.com/api/2/roles/%s';
    const DELETE_ROLE_URL = 'https://api.%s.onelogin.com/api/2/roles/%s';
    const GET_USERS_FOR_ROLE_URL = 'https://api.%s.onelogin.com/api/2/roles/%s/users';
    const ADD_USER_TO_ROLE_URL = 'https://api.%s.onelogin.com/api/2/roles/%s/users';
    const DELETE_USER_TO_ROLE_URL = 'https://api.%s.onelogin.com/api/2/roles/%s/users';


    public function createRole($roleParams)
    {
        $this->cleanError();
        $this->prepareToken();

        try {
            $url = $this->getURL(self::CREATE_ROLE_URL);
            $headers = $this->getAuthorizedHeader();

            $response = $this->client->post(
                $url,
                array(
                    'json' => $roleParams,
                    'headers' => $headers
                )
            );

            $data = json_decode($response->getBody());
            return $data;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $this->error = $response->getStatusCode();
            $this->errorDescription = $this->extractErrorMessageFromResponse($response);
            $this->errorAttribute = $this->extractErrorAttributeFromResponse($response);
        } catch (\Exception $e) {
            $this->error = 500;
            $this->errorDescription = $e->getMessage();
        }
    }

    public function updateRole($id, $roleParams)
    {
        $this->cleanError();
        $this->prepareToken();

        try {
            $url = $this->getURL(self::UPDATE_ROLE_URL, $id);
            $headers = $this->getAuthorizedHeader();

            $response = $this->client->put(
                $url,
                array(
                    'json' => $roleParams,
                    'headers' => $headers
                )
            );

            $data = json_decode($response->getBody());
            return $data;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $this->error = $response->getStatusCode();
            $this->errorDescription = $this->extractErrorMessageFromResponse($response);
            $this->errorAttribute = $this->extractErrorAttributeFromResponse($response);
        } catch (\Exception $e) {
            $this->error = 500;
            $this->errorDescription = $e->getMessage();
        }
    }

    public function deleteRole($id)
    {
        $this->cleanError();
        $this->prepareToken();

        try {
            $url = $this->getURL(self::DELETE_ROLE_URL, $id);
            $headers = $this->getAuthorizedHeader();

            $response = $this->client->delete(
                $url,
                array(
                    'headers' => $headers
                )
            );

            $data = json_decode($response->getBody());
            return $data;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $this->error = $response->getStatusCode();
            $this->errorDescription = $this->extractErrorMessageFromResponse($response);
            $this->errorAttribute = $this->extractErrorAttributeFromResponse($response);
        } catch (\Exception $e) {
            $this->error = 500;
            $this->errorDescription = $e->getMessage();
        }
        return false;
    }

    public function getRoleUsers($id)
    {
        $this->cleanError();
        $this->prepareToken();

        try {
            $url = $this->getURL(self::GET_USERS_FOR_ROLE_URL, $id);
            $headers = $this->getAuthorizedHeader();

            $response = $this->client->get(
                $url,
                array(
                    'headers' => $headers
                )
            );
            $data = json_decode($response->getBody());
            return $data;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $this->error = $response->getStatusCode();
            $this->errorDescription = $this->extractErrorMessageFromResponse($response);
        } catch (\Exception $e) {
            $this->error = 500;
            $this->errorDescription = $e->getMessage();
        }
    }

    public function assignUserToRole($id, $userIds)
    {
        $this->cleanError();
        $this->prepareToken();

        try {
            $url = $this->getURL(self::ADD_USER_TO_ROLE_URL, $id);
            $headers = $this->getAuthorizedHeader();

            $response = $this->client->post(
                $url,
                array(
                    'json' => $userIds,
                    'headers' => $headers
                )
            );
            $data = json_decode($response->getBody());
            return $data;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $this->error = $response->getStatusCode();
            $this->errorDescription = $this->extractErrorMessageFromResponse($response);
            $this->errorAttribute = $this->extractErrorAttributeFromResponse($response);
        } catch (\Exception $e) {
            $this->error = 500;
            $this->errorDescription = $e->getMessage();
        }
        return false;
    }

    public function removeUserFromRole($id, $userIds)
    {
        $this->cleanError();
        $this->prepareToken();

        try {
            $url = $this->getURL(self::DELETE_USER_TO_ROLE_URL, $id);
            $headers = $this->getAuthorizedHeader();

            $response = $this->client->delete(
                $url,
                array(
                    'json' => $userIds,
                    'headers' => $headers
                )
            );
            $data = json_decode($response->getBody());
            return $data;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $this->error = $response->getStatusCode();
            $this->errorDescription = $this->extractErrorMessageFromResponse($response);
            $this->errorAttribute = $this->extractErrorAttributeFromResponse($response);
        } catch (\Exception $e) {
            $this->error = 500;
            $this->errorDescription = $e->getMessage();
        }
        return false;
    }

}
