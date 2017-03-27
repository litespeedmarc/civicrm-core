<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2017                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *  Tests for MembershipRenewal, testing price set based renewals.
 *
 *  (PHP 5)
 *
 * @author Marc Brazeau <marc@scibrazeau.ca>
 */

/**
 *  Test CRM_Member_Form_MembershipRenewal functions.
 *
 * @package   CiviCRM
 * @group headless
 */
class CRM_Member_Form_MembershipPriceSetRenewalTest extends CiviUnitTestCase {

  private $_contactID;
  private $_paymentProcessorID;

  private $_dataset;
  private $_mem_date;

  public function setUp() {
    $this->_apiversion = 3;
    $this->_mem_date = date('Y-m-d');

    parent::setUp();

    $this->_paymentProcessorID = $this->processorCreate();


    // The CIC is the reference use case for this.  So we'll just go ahead and create their data.

    $op = new PHPUnit_Extensions_Database_Operation_Insert();

    $this->_dataset = $this->createFlatXMLDataSet(
      dirname(__FILE__) . '/dataset/price_set_renewal_data.xml'
    );

    $op->execute($this->_dbconn, $this->_dataset);
    // my xml left 0s out to be a bit more concise.  This creates errors, so use proper value.
    CRM_Core_DAO::executeQuery("update civicrm_price_field set is_required = 0 where is_required IS NULL");

  }

  public function testFormFieldsMembershipWithNoApplicablePriceSet() {
    try {
      $this->_contactID = $this->individualCreate();

      $membershipId = $this->cicContactMembershipCreate(150);

      $form = $this->getForm($membershipId);

      // we expect this to be false, because 150, has no price sets (even though there exists price sets in our data set).
      $this->assertNull($form->get_template_vars('show_price_sets'));
      $this->assertNull($form->get_template_vars('hasPriceSets'));
    } catch (Exception $e) {
      throw $e;
    }

  }


  public function testFormFieldsMembershipWithMultipleApplicablePriceSetsExpiredMemberships() {
    $this->_mem_date = '2007-01-21'; // noticed that this caused an exception, while running test below so keeping (good catch).
    $this->testFormFieldsMembershipWithMultipleApplicablePriceSets();
  }

  public function testFormFieldsMembershipWithMultipleApplicablePriceSets() {
    $this->_contactID = $this->individualCreate();

    $memberships[0] = $this->cicContactMembershipCreate(165); // ACCN Print (member of contact #8)
    $memberships[1] = $this->cicContactMembershipCreate(199); // CSC Full Fee (member of contact #5)
    $memberships[2] = $this->cicContactMembershipCreate(171); // Member of IUPAC  (member of contact #12).

    for ($i = 0; $i < sizeof($memberships); $i++) {
      // result is the same, regardless of membership that is renewed.
      $form = $this->getForm($memberships[$i]);

      // we expect this to be false, because 150, has no price sets (even though there exists price sets in our data set).
      $hasPriceSets =$form->get_template_vars('hasPriceSets');
      $showPriceSets =$form->get_template_vars('show_price_set');
      $price_set_id =$form->get_template_vars('priceSetId');
      $price_set =$form->get_template_vars('priceSet');
      $this->assertTrue($hasPriceSets);
      $this->assertFalse($showPriceSets);
      $this->assertEquals(32, $price_set_id);
      $this->assertNotEmpty($price_set);
    }
  }





  /**
   * Test the submit function of the membership form.
   */
  public function testSubmit() {
    $form = $this->getForm();
    $this->createLoggedInUser();
    $params = array(
      'cid' => $this->_contactID,
      'join_date' => date('m/d/Y', time()),
      'start_date' => '',
      'end_date' => '',
      // This format reflects the 23 being the organisation & the 25 being the type.
      'membership_type_id' => array(23, $this->membershipTypeAnnualFixedID),
      'auto_renew' => '0',
      'max_related' => '',
      'num_terms' => '1',
      'source' => '',
      'total_amount' => '50.00',
      //Member dues, see data.xml
      'financial_type_id' => '2',
      'soft_credit_type_id' => '',
      'soft_credit_contact_id' => '',
      'from_email_address' => '"Demonstrators Anonymous" <info@example.org>',
      'receipt_text_signup' => 'Thank you text',
      'payment_processor_id' => $this->_paymentProcessorID,
      'credit_card_number' => '4111111111111111',
      'cvv2' => '123',
      'credit_card_exp_date' => array(
        'M' => '9',
        'Y' => '2024', // TODO: Future proof
      ),
      'credit_card_type' => 'Visa',
      'billing_first_name' => 'Test',
      'billing_middlename' => 'Last',
      'billing_street_address-5' => '10 Test St',
      'billing_city-5' => 'Test',
      'billing_state_province_id-5' => '1003',
      'billing_postal_code-5' => '90210',
      'billing_country_id-5' => '1228',
    );
    $form->_contactID = $this->_contactID;

    $form->testSubmit($params);
    $membership = $this->callAPISuccessGetSingle('Membership', array('contact_id' => $this->_contactID));
    $this->callAPISuccessGetCount('ContributionRecur', array('contact_id' => $this->_contactID), 0);
    $contribution = $this->callAPISuccess('Contribution', 'get', array(
      'contact_id' => $this->_contactID,
      'is_test' => TRUE,
    ));

    $this->callAPISuccessGetCount('LineItem', array(
      'entity_id' => $membership['id'],
      'entity_table' => 'civicrm_membership',
      'contribution_id' => $contribution['id'],
    ), 1);
    $this->_checkFinancialRecords(array(
      'id' => $contribution['id'],
      'total_amount' => 50,
      'financial_account_id' => 2,
      'payment_instrument_id' => $this->callAPISuccessGetValue('PaymentProcessor', array(
        'id' => $this->_paymentProcessorID,
        'return' => 'payment_instrument_id',
      )),
    ), 'online');
  }



  /**
   * Clean up after each test.
   */
  public function tearDown() {
    try {

      // org contacts created.
      $this->contactDelete($this->_contactID);
      $op = new PHPUnit_Extensions_Database_Operation_Delete();
      $op->execute($this->_dbconn, $this->_dataset);

      $this->quickCleanup(
        array(
          'civicrm_address',
          'civicrm_membership',
          'civicrm_membership_type',
        )
      );

      $this->quickCleanUpFinancialEntities();

      for ($i = 5; $i < 13; $i++) {
        try {
          $this->contactDelete($i);
        } catch (Exception $e) {
          // ignore
        }
      }
    } catch (Exception $e) {
      throw $e;
    }



  }



  /**
   * Get a membership form object.
   *
   * We need to instantiate the form to run preprocess, which means we have to trick it about the request method.
   *
   * @param string $mode
   *
   * @return \CRM_Member_Form_MembershipRenewal
   */
  protected function getForm($membershipId, $mode = 'test') {
    $form = new CRM_Member_Form_MembershipRenewal();
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_REQUEST['cid'] = $this->_contactID;
    $_REQUEST['id'] = $membershipId;
    $form->controller = new CRM_Core_Controller();
    $form->_bltID = 5;
    $form->_mode = $mode;
    $form->buildForm();
    return $form;
  }

  public function cicContactMembershipCreate($membershipTypeId) {
    return $this->contactMembershipCreate(array(
      'contact_id' => $this->_contactID,
      'join_date' => $this->_mem_date,
      'start_date' => $this->_mem_date,
      'end_date' => $this->_mem_date,
      'status_id' => 3,
      'membership_type_id' => $membershipTypeId,
    ));
  }

}
