<?php

namespace App\Mail\Sales;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeliveryOrderApprovalRequestSent extends Mailable
{
    use Queueable, SerializesModels;

    public $deliveryOrders;
    public $approver;
    public $approverToken;
    public $form;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($deliveryOrders, $approver, $form)
    {
        $this->deliveryOrders = $deliveryOrders;
        $this->approver = $approver;
        $this->approverToken = $approver->token;
        $this->form = $form;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->approver->token = $this->approverToken;

        return $this->subject('Approval Email')
            ->view('emails.sales.delivery-order.delivery-order-approval-request', [
                'deliveryOrders' => $this->deliveryOrders,
                'approver' => $this->approver,
                'form' => $this->form
            ]);
    }
}
