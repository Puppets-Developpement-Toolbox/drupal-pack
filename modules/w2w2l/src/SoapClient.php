<?php

namespace Drupal\w2w2l;

use Drupal\w2w2l\ClientInterface;

class SoapClient extends \SoapClient implements ClientInterface {

  const SOAP_NAMESPACE = 'urn:enterprise.soap.sforce.com';


  /**
   * SOAP types derived from WSDL
   *
   * @var array
   */
  protected $types;


  public function __construct($wsdl, $login, $password, $token) 
  {
    parent::__construct($wsdl, [
      'trace'      => 1,
      'features'   => \SOAP_SINGLE_ELEMENT_ARRAYS,
    ]);
    
    $loginResponse = $this->login([
      'username'  => $login,
      'password'  => $password.$token
    ]);

    $this->__setSoapHeaders([
      new \SoapHeader(self::SOAP_NAMESPACE, 'SessionHeader', [
        'sessionId' => $loginResponse->result->sessionId
      ]),
      new \SoapHeader(self::SOAP_NAMESPACE, 'AssignmentRuleHeader', [
          'useDefaultRule' => true
      ])
    ]);
    $this->__setLocation($loginResponse->result->serverUrl);
  }


  /**
   * Retrieve SOAP types from the WSDL and parse them
   *
   * @return array    Array of types and their properties
   */
  public function getSoapTypes()
  {
    if (null === $this->types) {

      $soapTypes = $this->__getTypes();
      foreach ($soapTypes as $soapType) {
        $properties = array();
        $lines = explode("\n", $soapType);
        if (!preg_match('/struct (.*) {/', $lines[0], $matches)) {
          continue;
        }
        $typeName = $matches[1];

        foreach (array_slice($lines, 1) as $line) {
          if ($line == '}') {
            continue;
          }
          preg_match('/\s* (.*) (.*);/', $line, $matches);
          $properties[$matches[2]] = $matches[1];
        }

        // Since every object extends sObject, need to append sObject elements to all native and custom objects
        if ($typeName !== 'sObject' && array_key_exists('sObject', $this->types)) {
          $properties = array_merge($properties, $this->types['sObject']);
        }

        $this->types[$typeName] = $properties;
      }
    }

    return $this->types;
  }

  /**
   * Get a SOAP type’s elements
   *
   * @param string $type Object name
   * @return array       Elements for the type
   */

  /**
   * Get SOAP elements for a complexType
   *
   * @param string $complexType Name of SOAP complexType
   *
   * @return array  Names of elements and their types
   */
  public function getSoapElements($complexType)
  {
    $types = $this->getSoapTypes();
    if (isset($types[$complexType])) {
      return $types[$complexType];
    }
  }

  /**
   * Get a SOAP type’s element
   *
   * @param string $complexType Name of SOAP complexType
   * @param string $element     Name of element belonging to SOAP complexType
   *
   * @return string
   */
  public function getSoapElementType($complexType, $element)
  {
    $elements = $this->getSoapElements($complexType);
    if ($elements && isset($elements[$element])) {
      return $elements[$element];
    }
  }


  public function create($sobject, $type) 
  {
    return parent::create([
      'sObjects' => $this->createSoapVars([$sobject], $type)
    ]);
  }

  protected function createSoapVars(array $objects, $type)
  {
      $soapVars = [];

      foreach ($objects as $object) {

        $sObject = $this->createSObject($object, $type);

        $xml = '';
        if (isset($sObject->fieldsToNull)) {
            foreach ($sObject->fieldsToNull as $fieldToNull) {
                $xml .= '<fieldsToNull>' . $fieldToNull . '</fieldsToNull>';
            }
            $fieldsToNullVar = new \SoapVar(new \SoapVar($xml, XSD_ANYXML), SOAP_ENC_ARRAY);
            $sObject->fieldsToNull = $fieldsToNullVar;
        }

        $soapVar = new \SoapVar($sObject, SOAP_ENC_OBJECT, $type, self::SOAP_NAMESPACE);
        $soapVars[] = $soapVar;
      }

      return $soapVars;
  }

  protected function createSObject($object, $objectType)
  {
    $sObject = new \stdClass();

    foreach (get_object_vars($object) as $field => $value) {
        $type = $this->getSoapElementType($objectType, $field);
        if ($field != 'Id' && !$type) {
            continue;
        }

        if ($value === null) {
            $sObject->fieldsToNull[] = $field;
            continue;
        }

        // As PHP \DateTime to SOAP dateTime conversion is not done
        // automatically with the SOAP typemap for sObjects, we do it here.
        switch ($type) {
            case 'date':
                if ($value instanceof \DateTime) {
                    $value  = $value->format('Y-m-d');
                }
                break;
            case 'dateTime':
                if ($value instanceof \DateTime) {
                    $value  = $value->format('Y-m-d\TH:i:sP');
                }
                break;
            case 'base64Binary':
                $value = base64_encode($value);
                break;
        }

        $sObject->$field = $value;
    }

    return $sObject;
  }
}