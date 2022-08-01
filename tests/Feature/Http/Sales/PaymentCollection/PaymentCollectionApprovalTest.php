<?php

namespace Tests\Feature\Http\Sales\PaymentCollection;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Model\Sales\SalesInvoice\SalesInvoice;
use App\Model\Sales\SalesReturn\SalesReturn;
use App\Model\Sales\SalesDownPayment\SalesDownPayment;
use App\Model\Sales\PaymentCollection\PaymentCollection;
use App\Model\Sales\PaymentCollection\PaymentCollectionDetail;
use App\Model\Accounting\ChartOfAccountType;
use App\Model\Accounting\ChartOfAccount;
use App\Model\Master\Allocation;
use App\Model\Master\User as TenantUser;
use App\Model\Master\Customer;
use App\Model\Form;
use App\Model\Token;
use Tests\TestCase;

class PaymentCollectionApprovalTest extends TestCase
{

    public static $path = '/api/v1/sales/payment-collection';
    public static $pathApproval = '/api/v1/sales/approval/payment-collection';

    public function setUp(): void
    {
        parent::setUp();

        $this->signIn();
        $this->setProject();
        $_SERVER['HTTP_REFERER'] = 'http://www.example.com/';
    }

    public function createDummyUser() {
        $user = new TenantUser;
        $user->name = $this->faker->name;
        $user->address = $this->faker->address;
        $user->phone = $this->faker->phoneNumber;
        $user->email = $this->faker->email;
        $user->save();

        return $user;
    }

    public function dummyChartofAccount()
    {
        $user = $this->createDummyUser();

        $chartOfAccountTypeDebit = ChartOfAccountType::where('is_debit', 1)->first();
        
        if (!$chartOfAccountTypeDebit) {
            $chartOfAccountTypeDebit = new ChartOfAccountType;
            $chartOfAccountTypeDebit->name = 'OTHER EXPENSE';
            $chartOfAccountTypeDebit->alias = 'BEBAN NON OPERASIONAL';
            $chartOfAccountTypeDebit->is_debit = 1;

            $chartOfAccountTypeDebit->save();
        }

        $chartOfAccountTypeCredit = ChartOfAccountType::where('is_debit', 0)->first();
        if(!$chartOfAccountTypeCredit) {
            $chartOfAccountTypeCredit = new ChartOfAccountType;
            $chartOfAccountTypeCredit->name = 'OTHER INCOME';
            $chartOfAccountTypeCredit->alias = 'PENDAPATAN LAIN-LAIN';
            $chartOfAccountTypeCredit->is_debit = 0;

            $chartOfAccountTypeCredit->save();
        }        

        $chartOfAccountCredit = ChartOfAccount::where('position', 'CREDIT')->first();
        if(!$chartOfAccountCredit) {
            $chartOfAccountCredit = new ChartOfAccount;
            $chartOfAccountCredit->type_id = $chartOfAccountTypeDebit->id;
            $chartOfAccountCredit->position = 'CREDIT';
            $chartOfAccountCredit->number = '41106';
            $chartOfAccountCredit->name = 'OTHER INCOME';
            $chartOfAccountCredit->alias = 'PENDAPATAN LAIN-LAIN';
            $chartOfAccountCredit->created_by = $user->id;
            $chartOfAccountCredit->updated_by = $user->id;

            $chartOfAccountCredit->save();
        }

        $chartOfAccountDebit = ChartOfAccount::where('position', 'DEBIT')->first();
        if (!$chartOfAccountDebit) {
            $chartOfAccountDebit = new ChartOfAccount;
            $chartOfAccountDebit->type_id = $chartOfAccountTypeCredit->id;
            $chartOfAccountDebit->position = 'DEBIT';
            $chartOfAccountDebit->number = '51107';
            $chartOfAccountDebit->name = 'OFFICE ADMINISTRATION EXPENSE';
            $chartOfAccountDebit->alias = 'ADMINISTRASI BANK';
            $chartOfAccountDebit->created_by = $user->id;
            $chartOfAccountDebit->updated_by = $user->id;

            $chartOfAccountDebit->save();
        }        
    }

    public function dummySalesInvoice($customer)
    {
        $customer = Customer::orderBy('id', 'asc')->first();
        if (!$customer) {
            $customer = factory(Customer::class)->create();
        }        

        $salesInvoice = new SalesInvoice;
        $salesInvoice->customer_id = $customer->id;
        $salesInvoice->customer_name = $customer->name;
        $salesInvoice->amount = 2000000;

        $salesInvoice->save();

        $branch = $this->createBranch();

        $user = $this->createDummyUser();

        $defaultNumberPostfix = '{y}{m}{increment=4}';

        $lastForm = Form::where('formable_type', 'SalesInvoice')
                ->whereNotNull('number')
                ->where('increment_group', date("Ym"))
                ->orderBy('increment', 'desc')
                ->first();

        $form = new Form;
        $form->branch_id =  $branch->id;
        $form->date = date("Y-m-d H:i:s");
        $form->increment_group = date("Ym");
        $form->notes = "some notes";
        $form->created_by = $user->id;
        $form->updated_by = $user->id;
        $form->formable_id = $salesInvoice->id;
        $form->formable_type = 'SalesInvoice';
	    $form->generateFormNumber(
	        'SI'.$defaultNumberPostfix,
	        $salesInvoice->customer_id,
	        $salesInvoice->supplier_id
	    );
        $form->request_approval_to = $user->id;

        $form->save();        
    }

    public function dummySalesReturn($customer)
    {
        $salesInvoice  = SalesInvoice::orderBy('id', 'asc')->first();

        $salesReturn = new SalesReturn;
        $salesReturn->sales_invoice_id = $salesInvoice->id;
        $salesReturn->customer_id = $customer->id;
        $salesReturn->customer_name = $customer->name;
        $salesReturn->amount = 200000;

        $salesReturn->save();

        $branch = $this->createBranch();

        $user = $this->createDummyUser();

        $defaultNumberPostfix = '{y}{m}{increment=4}';

        $lastForm = Form::where('formable_type', 'SalesReturn')
                ->whereNotNull('number')
                ->where('increment_group', date("Ym"))
                ->orderBy('increment', 'desc')
                ->first();

        $form = new Form;
        $form->branch_id =  $branch->id;
        $form->date = date("Y-m-d H:i:s");
        $form->increment_group = date("Ym");
        $form->notes = "some notes";
        $form->created_by = $user->id;
        $form->updated_by = $user->id;
        $form->formable_id = $salesReturn->id;
        $form->formable_type = 'SalesReturn';
	    $form->generateFormNumber(
	        'SR'.$defaultNumberPostfix,
	        $salesReturn->customer_id,
	        $salesReturn->supplier_id
	    );
        $form->request_approval_to = $user->id;

        $form->save();   
    }

    public function dummyDownPayment($customer)
    {
        $salesDownPayment = new SalesDownPayment;
        $salesDownPayment->customer_id = $customer->id;
        $salesDownPayment->customer_name = $customer->name;
        $salesDownPayment->amount = 500000;

        $salesDownPayment->save();

        $branch = $this->createBranch();

        $user = $this->createDummyUser();

        $defaultNumberPostfix = '{y}{m}{increment=4}';

        $lastForm = Form::where('formable_type', 'SalesDownPayment')
                ->whereNotNull('number')
                ->where('increment_group', date("Ym"))
                ->orderBy('increment', 'desc')
                ->first();

        $form = new Form;
        $form->branch_id =  $branch->id;
        $form->date = date("Y-m-d H:i:s");
        $form->increment_group = date("Ym");
        $form->notes = "some notes";
        $form->created_by = $user->id;
        $form->updated_by = $user->id;
        $form->formable_id = $salesDownPayment->id;
        $form->formable_type = 'SalesDownPayment';
	    $form->generateFormNumber(
	        'DP'.$defaultNumberPostfix,
	        $salesDownPayment->customer_id,
	        $salesDownPayment->supplier_id
	    );
        $form->request_approval_to = $user->id;

        $form->save();
    }

    public function dummyData() {

        $customer = factory(Customer::class)->create();
        $this->dummySalesInvoice($customer);
        $this->dummySalesReturn($customer);
        $this->dummyDownPayment($customer);

        $salesInvoice  = SalesInvoice::orderBy('id', 'asc')->first();
        $salesReturn  = SalesReturn::orderBy('id', 'asc')->first();
        $salesDownPayment  = SalesDownPayment::orderBy('id', 'asc')->first();

        $otherIncome = ChartOfAccount::where('position', 'CREDIT')->first();
        $otherExpense = ChartOfAccount::where('position', 'DEBIT')->first();
        
        $customer = Customer::findOrFail($salesInvoice->customer->id);

        $user = new TenantUser;
        $user->name = $this->faker->name;
        $user->address = $this->faker->address;
        $user->phone = $this->faker->phoneNumber;
        $user->email = $this->faker->email;
        $user->save();

        $data = [
            "date" => date("Y-m-d H:i:s"),
            "increment_group" => date("Ym"),
            "notes" => "Some notes",
            "customer_id" => $customer->id,
            "customer_name" => $customer->name,
            "payment_type" => "cash",
            "request_approval_to" => $user->id,
            "details" => [
                [
                  "date" => date("Y-m-d H:i:s"),
                  "chart_of_account_id" => null,
                  "chart_of_account_name" => null,
                  "available" => $salesInvoice->amount,
                  "amount" => 800000,
                  "allocation_id" => null,
                  "allocation_name" => null,
                  "referenceable_form_date" => $salesInvoice->form->date,
                  "referenceable_form_number" => $salesInvoice->form->number,
                  "referenceable_form_notes" => $salesInvoice->form->notes,
                  "referenceable_id" => $salesInvoice->id,
                  "referenceable_type" => "SalesInvoice"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => null,
                    "chart_of_account_name" => null,
                    "available" => $salesReturn->amount,
                    "amount" => 80000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => $salesReturn->form->date,
                    "referenceable_form_number" => $salesReturn->form->number,
                    "referenceable_form_notes" => $salesReturn->form->notes,
                    "referenceable_id" => $salesReturn->id,
                    "referenceable_type" => "SalesReturn"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => null,
                    "chart_of_account_name" => null,
                    "available" => $salesDownPayment->amount,
                    "amount" => 200000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => $salesDownPayment->form->date,
                    "referenceable_form_number" => $salesDownPayment->form->number,
                    "referenceable_form_notes" => $salesDownPayment->form->notes,
                    "referenceable_id" => $salesDownPayment->id,
                    "referenceable_type" => "SalesDownPayment"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => $otherIncome->id,
                    "chart_of_account_name" => null,
                    "available" => 0,
                    "amount" => 200000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => null,
                    "referenceable_form_number" => null,
                    "referenceable_form_notes" => null,
                    "referenceable_id" => null,
                    "referenceable_type" => null
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => $otherExpense->id,
                    "chart_of_account_name" => null,
                    "available" => 0,
                    "amount" => 100000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => null,
                    "referenceable_form_number" => null,
                    "referenceable_form_notes" => null,
                    "referenceable_id" => null,
                    "referenceable_type" => null
                ],
            ]
        ];

        return $data;
    }

    public function dummyDataForReferenceDone() {

        $customer = factory(Customer::class)->create();
        $this->dummySalesInvoice($customer);
        $this->dummySalesReturn($customer);
        $this->dummyDownPayment($customer);

        $salesInvoice  = SalesInvoice::orderBy('id', 'asc')->first();
        $salesReturn  = SalesReturn::orderBy('id', 'asc')->first();
        $salesDownPayment  = SalesDownPayment::orderBy('id', 'asc')->first();

        $otherIncome = ChartOfAccount::where('position', 'CREDIT')->first();
        $otherExpense = ChartOfAccount::where('position', 'DEBIT')->first();
        
        $customer = Customer::findOrFail($salesInvoice->customer->id);

        $user = new TenantUser;
        $user->name = $this->faker->name;
        $user->address = $this->faker->address;
        $user->phone = $this->faker->phoneNumber;
        $user->email = $this->faker->email;
        $user->save();

        $data = [
            "date" => date("Y-m-d H:i:s"),
            "increment_group" => date("Ym"),
            "notes" => "Some notes",
            "customer_id" => $customer->id,
            "customer_name" => $customer->name,
            "payment_type" => "cash",
            "request_approval_to" => $user->id,
            "details" => [
                [
                  "date" => date("Y-m-d H:i:s"),
                  "chart_of_account_id" => null,
                  "chart_of_account_name" => null,
                  "available" => $salesInvoice->amount,
                  "amount" => $salesInvoice->amount,
                  "allocation_id" => null,
                  "allocation_name" => null,
                  "referenceable_form_date" => $salesInvoice->form->date,
                  "referenceable_form_number" => $salesInvoice->form->number,
                  "referenceable_form_notes" => $salesInvoice->form->notes,
                  "referenceable_id" => $salesInvoice->id,
                  "referenceable_type" => "SalesInvoice"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => null,
                    "chart_of_account_name" => null,
                    "available" => $salesReturn->amount,
                    "amount" => $salesReturn->amount,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => $salesReturn->form->date,
                    "referenceable_form_number" => $salesReturn->form->number,
                    "referenceable_form_notes" => $salesReturn->form->notes,
                    "referenceable_id" => $salesReturn->id,
                    "referenceable_type" => "SalesReturn"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => null,
                    "chart_of_account_name" => null,
                    "available" => $salesDownPayment->amount,
                    "amount" => $salesDownPayment->amount,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => $salesDownPayment->form->date,
                    "referenceable_form_number" => $salesDownPayment->form->number,
                    "referenceable_form_notes" => $salesDownPayment->form->notes,
                    "referenceable_id" => $salesDownPayment->id,
                    "referenceable_type" => "SalesDownPayment"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => $otherIncome->id,
                    "chart_of_account_name" => null,
                    "available" => 0,
                    "amount" => 200000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => null,
                    "referenceable_form_number" => null,
                    "referenceable_form_notes" => null,
                    "referenceable_id" => null,
                    "referenceable_type" => null
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => $otherExpense->id,
                    "chart_of_account_name" => null,
                    "available" => 0,
                    "amount" => 100000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => null,
                    "referenceable_form_number" => null,
                    "referenceable_form_notes" => null,
                    "referenceable_id" => null,
                    "referenceable_type" => null
                ],
            ]
        ];

        return $data;
    }

    public function dummySecondData() {

        $salesInvoice  = SalesInvoice::orderBy('id', 'asc')->first();
        $salesReturn  = SalesReturn::orderBy('id', 'asc')->first();
        $salesDownPayment  = SalesDownPayment::orderBy('id', 'asc')->first();

        $otherIncome = ChartOfAccount::where('position', 'CREDIT')->first();
        $otherExpense = ChartOfAccount::where('position', 'DEBIT')->first();
        
        $customer = Customer::findOrFail($salesInvoice->customer->id);

        $user = new TenantUser;
        $user->name = $this->faker->name;
        $user->address = $this->faker->address;
        $user->phone = $this->faker->phoneNumber;
        $user->email = $this->faker->email;
        $user->save();

        $data = [
            "date" => date("Y-m-d H:i:s"),
            "increment_group" => date("Ym"),
            "notes" => "Some notes",
            "customer_id" => $salesInvoice->customer->id,
            "customer_name" => $salesInvoice->customer->name,
            "payment_type" => "cash",
            "request_approval_to" => $user->id,
            "details" => [
                [
                  "date" => date("Y-m-d H:i:s"),
                  "chart_of_account_id" => null,
                  "chart_of_account_name" => null,
                  "available" => $salesInvoice->amount,
                  "amount" => 400000,
                  "allocation_id" => null,
                  "allocation_name" => null,
                  "referenceable_form_date" => $salesInvoice->form->date,
                  "referenceable_form_number" => $salesInvoice->form->number,
                  "referenceable_form_notes" => $salesInvoice->form->notes,
                  "referenceable_id" => $salesInvoice->id,
                  "referenceable_type" => "SalesInvoice"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => null,
                    "chart_of_account_name" => null,
                    "available" => $salesReturn->amount,
                    "amount" => 40000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => $salesReturn->form->date,
                    "referenceable_form_number" => $salesReturn->form->number,
                    "referenceable_form_notes" => $salesReturn->form->notes,
                    "referenceable_id" => $salesReturn->id,
                    "referenceable_type" => "SalesReturn"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => null,
                    "chart_of_account_name" => null,
                    "available" => $salesDownPayment->amount,
                    "amount" => 100000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => $salesDownPayment->form->date,
                    "referenceable_form_number" => $salesDownPayment->form->number,
                    "referenceable_form_notes" => $salesDownPayment->form->notes,
                    "referenceable_id" => $salesDownPayment->id,
                    "referenceable_type" => "SalesDownPayment"
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => $otherIncome->id,
                    "chart_of_account_name" => null,
                    "available" => 0,
                    "amount" => 200000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => null,
                    "referenceable_form_number" => null,
                    "referenceable_form_notes" => null,
                    "referenceable_id" => null,
                    "referenceable_type" => null
                ],
                [
                    "date" => date("Y-m-d H:i:s"),
                    "chart_of_account_id" => $otherExpense->id,
                    "chart_of_account_name" => null,
                    "available" => 0,
                    "amount" => 100000,
                    "allocation_id" => null,
                    "allocation_name" => null,
                    "referenceable_form_date" => null,
                    "referenceable_form_number" => null,
                    "referenceable_form_notes" => null,
                    "referenceable_id" => null,
                    "referenceable_type" => null
                ],
            ]
        ];

        return $data;
    }

    public function addUpdateHistory($id) {
        $dataHistories = [
            "id" => $id,
            "activity" => "update"
        ];

        $responseAddHistory = $this->json('POST', self::$path.'/histories', $dataHistories, [$this->headers]);
        return $responseAddHistory;
    }

    public function testCreate()
    {
        
        $this->dummyChartofAccount();

        $data = $this->dummyData();
        
        $response = $this->json('POST', self::$path, $data, $this->headers);

        $response->assertStatus(201);
    }

    public function testSendApprovalPaymentCollection()
    {
        // send approval
        $this->testCreate();

        $salesPaymentCollection = PaymentCollection::orderBy('id', 'desc')->first();

        $data = [
            "id" => $salesPaymentCollection->id,
            "form_send_done" => 1,
            "crud_type" => "update"
        ];

        $response = $this->json('POST', self::$pathApproval.'/'.$salesPaymentCollection->id.'/send', $data, $this->headers);
        
        $response->assertStatus(200);

        // send update approval
        $data = [
            "id" => $salesPaymentCollection->id,
            "form_send_done" => 1,
            "crud_type" => "update"
        ];

        $token = Token::where('user_id', $salesPaymentCollection->form->request_approval_to)->first();
        if ($token) {
            $token->delete();
        }

        $responseAddHistory = $this->addUpdateHistory($salesPaymentCollection->id);

        $response = $this->json('POST', self::$pathApproval.'/'.$salesPaymentCollection->id.'/send', $data, $this->headers);
        
        $response->assertStatus(200);

        // send cancellation
        $data = [
            "id" => $salesPaymentCollection->id
        ];
        $this->json('DELETE', self::$path.'/'.$salesPaymentCollection->id, [], [$this->headers]);

        $token = Token::where('user_id', $salesPaymentCollection->form->request_approval_to)->first();
        if ($token) {
            $token->delete();
        }

        $response = $this->json('POST', self::$pathApproval.'/cancellation/'.$salesPaymentCollection->id.'/send', $data, $this->headers);
        
        $response->assertStatus(200);
    }

    public function testListApprovalAll()
    {
        $this->testCreate();


        $response = $this->json('GET', self::$pathApproval.'?limit=10&page=1', $this->headers);
        
        $response->assertStatus(200);
    }

    public function testApproveApproval()
    {
        // reference still pending
        $this->testCreate();

        $salesPaymentCollection = PaymentCollection::orderBy('id', 'desc')->first();
        
        $data = [
            "id" => $salesPaymentCollection->id
        ];

        $response = $this->json('POST', self::$path.'/'.$salesPaymentCollection->id.'/approve', $data, $this->headers);
        
        $response->assertStatus(200);

        //reference done
        $this->dummyChartofAccount();

        $createData = $this->dummyDataForReferenceDone();
        
        $response = $this->json('POST', self::$path, $createData, $this->headers);
        
        $salesPaymentCollection = PaymentCollection::orderBy('id', 'desc')->first();
        
        $data = [
            "id" => $salesPaymentCollection->id
        ];

        $response = $this->json('POST', self::$path.'/'.$salesPaymentCollection->id.'/approve', $data, $this->headers);
        
        $response->assertStatus(200);
    }

    public function testApproveApprovalNotUpdatingIfNotEnoughAmountToCollect()
    {
        $this->dummyChartofAccount();

        $createData = $this->dummyDataForReferenceDone();
        $this->json('POST', self::$path, $createData, $this->headers);

        $salesPaymentCollection = PaymentCollection::orderBy('id', 'desc')->first();
        
        $data = [
            "id" => $salesPaymentCollection->id
        ];

        $this->json('POST', self::$path.'/'.$salesPaymentCollection->id.'/approve', $data, $this->headers);

        $createSecondData = $this->dummySecondData();
        $this->json('POST', self::$path, $createSecondData, $this->headers);

        $salesPaymentCollection = PaymentCollection::orderBy('id', 'desc')->first();
        
        $data = [
            "id" => $salesPaymentCollection->id
        ];

        $response = $this->json('POST', self::$path.'/'.$salesPaymentCollection->id.'/approve', $data, $this->headers);
        
        $response->assertStatus(200);
    }

    public function testRejectApproval()
    {
        // success
        $this->testCreate();

        $salesPaymentCollection = PaymentCollection::orderBy('id', 'desc')->first();
        
        $data = [
            "id" => $salesPaymentCollection->id,
            "reason" => "some reason"
        ];

        $response = $this->json('POST', self::$path.'/'.$salesPaymentCollection->id.'/reject', $data, $this->headers);
        
        $response->assertStatus(200);

        // fail
        $this->testCreate();

        $salesPaymentCollection = PaymentCollection::orderBy('id', 'desc')->first();
        
        $data = [
            "id" => $salesPaymentCollection->id
        ];

        $response = $this->json('POST', self::$path.'/'.$salesPaymentCollection->id.'/reject', $data, $this->headers);
        
        $response->assertStatus(500);
    }

    public function testSendApprovalAll()
    {
        $this->testCreate();

        $paymentCollectionData = $this->dummySecondData();
        
        $response = $this->json('POST', self::$path, $paymentCollectionData, $this->headers);
        $response = $this->json('POST', self::$path, $paymentCollectionData, $this->headers);
        $response = $this->json('POST', self::$path, $paymentCollectionData, $this->headers);

        $salesPaymentCollections = PaymentCollection::orderBy('id', 'desc')->take(4)->get();

        $ids = [];
        $idx = 0;
        foreach ($salesPaymentCollections as $paymentCollection) {
            if ($idx === 1) {
                $updateData = $this->dummySecondData();

                $updateData["id"] = $paymentCollection->id;

                $responseUpdate = $this->json('PATCH', self::$path.'/'.$paymentCollection->id, $updateData, [$this->headers]);

                $dataHistories = [
                    "id" => $paymentCollection->id,
                    "activity" => "update"
                ];

                $responseAddHistory = $this->addUpdateHistory($paymentCollection->id);

            }
            if ($idx === 2) {
                $responseDelete = $this->json('DELETE', self::$path.'/'.$paymentCollection->id, [], [$this->headers]);
            }
            $id = [ "id" => $paymentCollection->id];
            array_push($ids, $id);
            $idx++;
        }

        $data = [
            "ids" => $ids
        ];

        $response = $this->json('POST', '/api/v1/sales/approval/payment-collection/send', $data, $this->headers);
        
        $response->assertStatus(200);
    }
}
