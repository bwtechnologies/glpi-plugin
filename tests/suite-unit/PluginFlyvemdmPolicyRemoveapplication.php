<?php
/**
 * LICENSE
 *
 * Copyright © 2016-2018 Teclib'
 * Copyright © 2010-2018 by the FusionInventory Development Team.
 *
 * This file is part of Flyve MDM Plugin for GLPI.
 *
 * Flyve MDM Plugin for GLPI is a subproject of Flyve MDM. Flyve MDM is a mobile
 * device management software.
 *
 * Flyve MDM Plugin for GLPI is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * Flyve MDM Plugin for GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with Flyve MDM Plugin for GLPI. If not, see http://www.gnu.org/licenses/.
 * ------------------------------------------------------------------------------
 * @author    Domingo Oropeza
 * @copyright Copyright © 2018 Teclib
 * @license   AGPLv3+ http://www.gnu.org/licenses/agpl.txt
 * @link      https://github.com/flyve-mdm/glpi-plugin
 * @link      https://flyve-mdm.com/
 * ------------------------------------------------------------------------------
 */

namespace tests\units;

use Flyvemdm\Tests\CommonTestCase;

class PluginFlyvemdmPolicyRemoveapplication extends CommonTestCase {

   private $dataField = [
      'group'     => 'application',
      'symbol'    => 'removeApp',
      'type_data' => '',
      'unicity'   => 0,
   ];

   private $packageName = 'com.domain.author.application.apk';
   private $filename = 'application.apk';

   /**
    * @param null|\PluginFlyvemdmPolicy $policyData
    * @return array
    */
   private function createNewPolicyInstance($policyData = null) {
      if (null === $policyData) {
         $policyData = new \PluginFlyvemdmPolicy();
         $policyData->fields = $this->dataField;
      }
      $policy = $this->newTestedInstance($policyData);
      return [$policy, $policyData];
   }

   /**
    * @return array
    */
   protected function policyInputProvider() {
      return [
         'Check Invalid arguments 1' => [
            'data'     => ['value' => '', 'itemType' => '', 'itemId' => ''],
            'expected' => ['return' => false, 'message' => 'An application ID is required'],
         ],
         'Check Invalid arguments 2' => [
            'data'     => ['value' => 'fake.lorem.package', 'itemType' => 'ipsum', 'itemId' => 0],
            'expected' => ['return' => false],
         ],
         'Check Invalid arguments 3' => [
            'data'     => ['value' => 'fake.lorem.package', 'itemType' => '', 'itemId' => 1],
            'expected' => ['return' => false],
         ],
         'Valid check 1'             => [
            'data'     => ['value' => 'fake.lorem.package', 'itemType' => '', 'itemId' => 0],
            'expected' => ['return' => true],
         ],
      ];
   }

   /**
    * @dataProvider policyInputProvider
    * @tags testIntegrityCheck
    *
    * @param array $data
    * @param array $expected
    */
   public function testIntegrityCheck(array $data, array $expected) {
      list($policy) = $this->createNewPolicyInstance();
      $success = $policy->integrityCheck($data['value'], $data['itemType'], $data['itemId']);
      $this->boolean($success)->isEqualTo($expected['return']);
      if (!$expected['return'] && isset($expected['message'])) {
         $this->string($_SESSION["MESSAGE_AFTER_REDIRECT"][0][0])->isEqualTo($expected['message']);
      }
      unset($_SESSION["MESSAGE_AFTER_REDIRECT"]);
   }

   /**
    * @return array
    */
   public function unicityCheckProvider() {
      return [
         'Check for invalid package' => [
            'data'     => ['value' => 'not_existing_package'],
            'expected' => ['return' => true],
         ],
         // TODO: For test this case a task must be created first
         /*'Check for valid package' => [
            'data'     => ['value' => 'package_name'],
            'expected' => ['return' => false],
         ],*/
      ];
   }

   /**
    * @dataProvider unicityCheckProvider
    * @tags testUnicityCheck
    *
    * @param array $data
    * @param array $expected
    */
   public function testUnicityCheck(array $data, array $expected) {
      list($policy) = $this->createNewPolicyInstance();
      $mockInstance = $this->newMockInstance('\PluginFlyvemdmFleet');
      $mockInstance->getMockController()->getID = 2;
      if (!$expected['return']) {
         $application = $this->createAppInDB();
         $data['value'] = $application->getField('package_name');
      }
      $this->boolean($policy->unicityCheck($data['value'], '', '',
         $mockInstance))->isEqualTo($expected['return']);
   }

   /**
    * @return array
    */
   public function applyProvider() {
      $package = $this->createAppInDB();
      return [
         'Check for invalid arguments 1' => [
            'data'     => ['value' => 'fake.lorem.package'],
            'expected' => ['return' => false, 'message' => 'Policy not found'],
         ],
         'Check for correct value'       => [
            'data'     => ['value' => $package->getField('package_name')],
            'expected' => ['return' => true],
         ],
      ];
   }

   /**
    * @dataProvider applyProvider
    * @tags testApply
    *
    * @param array $data
    * @param array $expected
    */
   public function testApply(array $data, array $expected) {
      $policyData = null;
      if (true === $expected['return']) {
         $policyData = new \PluginFlyvemdmPolicy();
         $policyData->getFromDBBySymbol('removeApp');
      }
      list($policy) = $this->createNewPolicyInstance($policyData);
      $mockInstance = $this->newMockInstance('\PluginFlyvemdmFleet');
      $mockInstance->getMockController()->getID = 1;
      if (true == $expected['return']) {
         // the fleet must exist if we expect a true action.
         $mockInstance = $this->createFleetInDB();
      }
      $result = $policy->apply($mockInstance, $data['value'], '', '');
      $this->boolean($result)->isEqualTo($expected['return']);
      if (!$expected['return'] && isset($expected['message'])) {
         $this->string($_SESSION["MESSAGE_AFTER_REDIRECT"][1][0])->isEqualTo($expected['message']);
      }
      unset($_SESSION["MESSAGE_AFTER_REDIRECT"]); // to clear the buffer
   }

   /**
    * @tags testGetMqttMessage
    */
   public function testGetMqttMessage() {
      list($policy) = $this->createNewPolicyInstance();
      $this->boolean($policy->getMqttMessage(null, null, null))->isFalse();

      $result = $policy->getMqttMessage($packageName = 'fake.lorem.ipsum.package', '', 0);
      $this->array($result)->hasKeys([$this->dataField['symbol']])
         ->string($result[$this->dataField['symbol']])->isEqualTo($packageName);
   }

   /**
    * Create an application (directly in DB) because we are not uploading any file
    * @return \PluginFlyvemdmPackage
    */
   private function createAppInDB() {
      global $DB;

      $uniqid = uniqid();
      $table_file = \PluginFlyvemdmPackage::getTable();
      $query = "INSERT INTO `$table_file` (
        `package_name`,
        `alias`,
        `version`,
        `filename`,
        `entities_id`,
        `dl_filename`,
        `icon`
        ) VALUES (
        '" . $uniqid . $this->packageName . "',
        'application',
        '1.0.5',
        '0/" . $uniqid . $this->filename . "',
        '0',
        '" . $this->filename . "',
        ''
        )";
      $result = $DB->query($query);
      $this->boolean($result)->isTrue();

      $file = new \PluginFlyvemdmPackage();
      $file->getFromDB($DB->insert_id());
      $this->boolean($file->isNewItem())->isFalse();

      return $file;
   }

   /**
    * Create an application (directly in DB) because we are not uploading any file
    * @return \PluginFlyvemdmPackage
    */
   private function createFleetInDB() {
      global $DB;

      $uniqid = 'packages_fleet_' . uniqid();
      $table_file = \PluginFlyvemdmFleet::getTable();
      $query = "INSERT INTO `$table_file` (
        `name`
        ) VALUES (
        '" . $uniqid . "'
        )";
      $result = $DB->query($query);
      $this->boolean($result)->isTrue();

      $itemType = new \PluginFlyvemdmFleet();
      $itemType->getFromDB($DB->insert_id());
      $this->boolean($itemType->isNewItem())->isFalse();

      return $itemType;
   }
}