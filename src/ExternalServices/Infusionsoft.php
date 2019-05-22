<?php

namespace Railroad\EventDataSynchronizer\ExternalServices;

use Exception;
use Illuminate\Log\Logger;
use iSDK;

class Infusionsoft
{
    private $iSDK;
    private $logWriter;

    const DATE_FORMAT = 'm/d/Y';

    /**
     * *** WARNING ***
     *
     * When this class gets constructed it WILL make a curl call to infusionsoft.
     * Be careful where this gets dependency injected.
     *
     * @param iSDK $iSDK
     * @param Logger $logWriter
     */

    public function __construct(iSDK $iSDK, Logger $logWriter)
    {
        $this->iSDK = $iSDK;
        $this->logWriter = $logWriter;

        try {
            $this->iSDK->cfgCon(
                config('event-data-synchronizer.infusionsoft_app_name'),
                config('event-data-synchronizer.infusionsoft_api_key'),
                config('event-data-synchronizer.infusionsoft_db_on')
            );
        } catch (Exception $exception) {
            $this->logWriter->emergency('Event data synchronizer could not connect to Infusionsoft API: ' . $exception->getMessage());
        }
    }

    public function infusionsoftDate($dateStr, $dateFormat = 'US')
    {
        return $this->iSDK->infuDate($dateStr, $dateFormat);
    }

    /**
     * @param string $tName
     * @param int $limit
     * @param int $page
     * @param array $query
     * @param array $rFields
     * @return array
     */
    public function dsQuery($tName, $limit, $page, $query, $rFields)
    {
        $response = $this->iSDK->dsQuery($tName, $limit, $page, $query, $rFields);

        if (is_string($response) && false !== strpos(strtolower($response), 'error')) {
            $this->logWriter->error(
                'Infusionsoft Error: response:' .
                $response .
                ', Table Name: ' .
                $tName .
                ', Limit: ' .
                $limit .
                ', Page: ' .
                $page .
                ', Query: ' .
                var_export($query, true) .
                ', Fields: ' .
                var_export($rFields, true)
            );

            return [];
        }

        return $response;
    }

    /**
     * @param string $tName
     * @param int $id
     * @param array $iMap
     * @return int
     */
    public function dsUpdate($tName, $id, $iMap)
    {
        $response = $this->iSDK->dsUpdate($tName, $id, $iMap);

        return $response;
    }

    /**
     * @param string $integration
     * @param string $callName
     * @param int $contactId
     * @return array
     */
    public function achieveGoal($integration, $callName, $contactId)
    {
        $response = $this->iSDK->achieveGoal($integration, $callName, $contactId);

        return $response;
    }

    /**
     * @param array $ids
     * @return bool
     */
    public function mergeContacts($ids)
    {
        for ($i = 1; $i < count($ids); $i++) {
            $response = $this->iSDK->mergeCon($ids[0], $ids[$i]);

            if ($response !== true) {
                $this->logWriter->error(
                    'Infusionsoft Error: response:' .
                    $response .
                    ', Contact ID 1: ' .
                    $ids[0] .
                    ', Contact ID 2: ' .
                    $ids[$i]
                );
            }
        }

        return true;
    }

    /**
     * @param string $tName
     * @param int $limit
     * @param int $page
     * @param array $query
     * @param array $rFields
     * @param string $orderByField
     * @param bool $ascending
     * @return array
     */
    public function dsQueryOrderBy($tName, $limit, $page, $query, $rFields, $orderByField, $ascending = true)
    {
        return $this->iSDK->dsQueryOrderBy(
            $tName,
            $limit,
            $page,
            $query,
            $rFields,
            $orderByField,
            $ascending
        );
    }

    /**
     * @param integer $contactId
     * @return array
     */
    public function getTagsForContact($contactId)
    {
        $response = $this->iSDK->dsQuery(
            'ContactGroupAssign',
            1000,
            0,
            array(
                'contactId' => $contactId
            ),
            array(
                'ContactId',
                'GroupId',
            )
        );

        if ($response === false ||
            (is_string($response) && false !== strpos(strtolower($response), 'error'))
        ) {
            $this->logWriter->error(
                'Infusionsoft Error: response:' . var_export($response, true) . ', Contact ID: ' . $contactId
            );
        }

        return $response;
    }

    /**
     * @param $contactId
     * @param array $tagIdsToAdd
     * @return int
     */
    public function addTagsToContact($contactId, array $tagIdsToAdd)
    {
        $amountOfTagsAdded = 0;

        foreach ($tagIdsToAdd as $tagId) {
            $response = $this->iSDK->grpAssign($contactId, $tagId);

            if ($response == false) {
                $this->logWriter->error(
                    'Infusionsoft Error: Failed to add ' . $contactId . ' to tag group ' . $tagId
                );
            }

            $amountOfTagsAdded++;
        }

        return $amountOfTagsAdded;
    }

    /**
     * @param $contactId
     * @param array $tagIdsToRemove
     * @return int
     */
    public function removeTagsFromContact($contactId, array $tagIdsToRemove)
    {
        $amountOfTagsRemoved = 0;

        foreach ($tagIdsToRemove as $tagId) {
            $response = $this->iSDK->grpRemove($contactId, $tagId);

            if ($response == false) {
                $this->logWriter->error(
                    'Infusionsoft Error: Failed to remove ' . $contactId . ' from groups ' . $tagId
                );
            }

            $amountOfTagsRemoved++;
        }

        return $amountOfTagsRemoved;
    }

    /**
     * @param $name
     * @return int
     */
    public function syncTag($name)
    {
        $tags = $this->getAllTags();

        foreach ($tags as $tag) {
            if ($tag['GroupName'] == $name) {
                return $tag['Id'];
            }
        }

        return $this->createTag($name);
    }

    /**
     * @return array
     */
    public function getAllTags()
    {
        $response = $this->iSDK->dsQuery(
            "ContactGroup",
            1000,
            0,
            array('Id' => '%'),
            array(
                'Id',
                'GroupName'
            )
        );

        if ($response === false ||
            (is_string($response) && false !== strpos(strtolower($response), 'error'))) {
            $this->logWriter->error(
                'Infusionsoft Error: Could not get all tags. response:' . $response
            );

            return [];
        }

        return $response;
    }

    /**
     * @param $name
     * @return int // tag id
     */
    public function createTag($name)
    {
        $response = $this->iSDK->dsAdd(
            'ContactGroup',
            array(
                'GroupName' => $name,
            )
        );

        if ($response == false ||
            (is_string($response) && false !== strpos(strtolower($response), 'error'))
        ) {
            $this->logWriter->error(
                'Infusionsoft Error: Could not create tag. response:' . $response . ', Tag Name: ' . $name
            );
        }

        return $response;
    }

    /**
     * @param $contactDetails
     * @return int // contact id
     */
    public function createContact($contactDetails)
    {
        $contactId = $this->iSDK->addCon($contactDetails);

        if ($contactId == false ||
            (is_string($contactId) && false !== strpos(strtolower($contactId), 'error'))
        ) {
            $this->logWriter->error(
                'Infusionsoft Error: Could not create contact. response:' .
                $contactId .
                ', Data: ' .
                var_export($contactDetails, true)
            );
        }

        return $contactId;
    }

    /**
     * @param $email
     * @return array
     */
    public function getContactsByEmail($email)
    {
        $contacts = $this->iSDK->findByEmail($email, array('Id'));

        if (!is_array($contacts)) {
            $this->logWriter->error(
                'Infusionsoft Error: Could not get contacts by email. response:' .
                $contacts .
                ', Email: ' .
                $email
            );

            return [];
        }

        return $contacts;
    }

    /**
     * @param $id
     * @param array $updatedDetails
     * @return int
     */
    public function updateContact($id, $updatedDetails)
    {
        $updatedContactId = $this->iSDK->dsUpdate('Contact', $id, $updatedDetails);

        if ($updatedContactId == false ||
            (is_string($updatedContactId) &&
                false !== strpos(strtolower($updatedContactId), 'error'))
        ) {
            $this->logWriter->error(
                'Infusionsoft Error: Could not update contact. response:' .
                $updatedContactId .
                ', Contact Id: ' .
                $id .
                ', Updated Details: ' .
                var_export($updatedDetails, true)
            );
        }

        return $updatedContactId;
    }

    /**
     * @param integer $id
     * @return array // contact info array
     */
    public function getContactById($id)
    {
        $contacts = $this->iSDK->dsQuery(
            'Contact',
            1,
            0,
            array('Id' => $id),
            array(
                'AccountId',
                'Address1Type',
                'Address2Street1',
                'Address2Street2',
                'Address2Type',
                'Address3Street1',
                'Address3Street2',
                'Address3Type',
                'Anniversary',
                'AssistantName',
                'AssistantPhone',
                'BillingInformation',
                'Birthday',
                'City',
                'City2',
                'City3',
                'Company',
                'CompanyID',
                'ContactNotes',
                'ContactType',
                'Country',
                'Country2',
                'Country3',
                'CreatedBy',
                'DateCreated',
                'Email',
                'EmailAddress2',
                'EmailAddress3',
                'Fax1',
                'Fax1Type',
                'Fax2',
                'Fax2Type',
                'FirstName',
                'Groups',
                'Id',
                'JobTitle',
                'LastName',
                'LastUpdated',
                'LastUpdatedBy',
                'LeadSourceId',
                'Leadsource',
                'MiddleName',
                'Nickname',
                'OwnerID',
                'Password',
                'Phone1',
                'Phone1Ext',
                'Phone1Type',
                'Phone2',
                'Phone2Ext',
                'Phone2Type',
                'Phone3',
                'Phone3Ext',
                'Phone3Type',
                'Phone4',
                'Phone4Ext',
                'Phone4Type',
                'Phone5',
                'Phone5Ext',
                'Phone5Type',
                'PostalCode',
                'PostalCode2',
                'PostalCode3',
                'ReferralCode',
                'SpouseName',
                'State',
                'State2',
                'State3',
                'StreetAddress1',
                'StreetAddress2',
                'Suffix',
                'Title',
                'Username',
                'Validated',
                'Website',
                'ZipFour1',
                'ZipFour2',
                'ZipFour3'
            )
        );

        if ($contacts === false ||
            (is_string($contacts) && false !== strpos(strtolower($contacts), 'error')) ||
            count($contacts) < 1
        ) {
            $this->logWriter->error(
                'Infusionsoft Error: Could not get contact by id. response:' .
                $contacts .
                ', Contact Id: ' .
                $id
            );
        }

        return $contacts[0];
    }

    /**
     * @param $email
     * @param $Address2Street1
     * @param $Address2Street2
     * @param $Address2Type
     * @param $City
     * @param $City2
     * @param $Country
     * @param $Country2
     * @param $FirstName
     * @param $LastName
     * @param $Phone1
     * @param $PostalCode
     * @param $PostalCode2
     * @param $State
     * @param $State2
     * @param $StreetAddress1
     * @param $StreetAddress2
     * @return array
     */
    public function syncContactsForEmail(
        $email,
        $Address2Street1,
        $Address2Street2,
        $Address2Type,
        $City,
        $City2,
        $Country,
        $Country2,
        $FirstName,
        $LastName,
        $Phone1,
        $PostalCode,
        $PostalCode2,
        $State,
        $State2,
        $StreetAddress1,
        $StreetAddress2
    ) {
        $infContacts = $this->getContactsByEmail($email);

        if (count($infContacts) > 1) {
            $contactIdsToMerge = [];

            foreach ($infContacts as $infContact) {
                $contactIdsToMerge[] = $infContact['Id'];
            }

            $this->mergeContacts($contactIdsToMerge);
        }

        if (count($infContacts) == 0) {
            $contactId = $this->createContact(
                array(
                    'Email' => $email,
                    'Address2Street1' => $Address2Street1,
                    'Address2Street2' => $Address2Street2,
                    'Address2Type' => $Address2Type,
                    'City' => $City,
                    'City2' => $City2,
                    'Country' => $Country,
                    'Country2' => $Country2,
                    'FirstName' => $FirstName,
                    'LastName' => $LastName,
                    'Phone1' => $Phone1,
                    'PostalCode' => $PostalCode,
                    'PostalCode2' => $PostalCode2,
                    'State' => $State,
                    'State2' => $State2,
                    'StreetAddress1' => $StreetAddress1,
                    'StreetAddress2' => $StreetAddress2
                )
            );

        } else {
            $infContact = $this->getContactById($infContacts[0]['Id']);

            $contactId = $this->updateContact(
                $infContact['Id'],
                array(
                    'Address2Street1' => $Address2Street1,
                    'Address2Street2' => $Address2Street2,
                    'Address2Type' => $Address2Type,
                    'City' => $City,
                    'City2' => $City2,
                    'Country' => $Country,
                    'Country2' => $Country2,
                    'FirstName' => $FirstName,
                    'LastName' => $LastName,
                    'Phone1' => $Phone1,
                    'PostalCode' => $PostalCode,
                    'PostalCode2' => $PostalCode2,
                    'State' => $State,
                    'State2' => $State2,
                    'StreetAddress1' => $StreetAddress1,
                    'StreetAddress2' => $StreetAddress2
                )
            );

        }

        return $this->getContactById($contactId);
    }

    /**
     * @param $email
     * @return integer // contact id
     */
    public function syncContactsForEmailOnly($email)
    {
        $infContacts = $this->getContactsByEmail($email);

        if (count($infContacts) > 1) {
            $contactIdsToMerge = [];

            foreach ($infContacts as $infContact) {
                $contactIdsToMerge[] = $infContact['Id'];
            }

            $this->mergeContacts($contactIdsToMerge);
        }

        if (count($infContacts) == 0) {
            $contactId = $this->createContact(
                array(
                    'Email' => $email,
                )
            );
        } else {
            $contactId = $infContacts[0]['Id'];
        }

        return $contactId;
    }
}