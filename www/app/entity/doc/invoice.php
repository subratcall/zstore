<?php

namespace App\Entity\Doc;

use App\Helper as H;

/**
 * Класс-сущность  документ счет фактура
 *
 */
class Invoice extends \App\Entity\Doc\Document
{

    public function generateReport() {

        $firm = H::getFirmData(  $this->headerdata["firm_id"],$this->branch_id);


        $i = 1;
        $detail = array();

        foreach ($this->unpackDetails('detaildata') as $item) {

            if (isset($detail[$item->item_id])) {
                $detail[$item->item_id]['quantity'] += $item->quantity;
            } else {
                $detail[] = array("no" => $i++,
                    "tovar_name" => $item->itemname,
                    "tovar_code" => $item->item_code,
                    "quantity" => H::fqty($item->quantity),
                    "price" => H::fa($item->price),
                    "msr" => $item->msr,
                    "amount" => H::fa($item->quantity * $item->price)
                );
            }
        }

        $totalstr = H::sumstr($this->amount);

        $header = array('date' => H::fd($this->document_date),
            "_detail" => $detail,
            "customer_name" => $this->customer_name,
            "firm_name" => $firm['firm_name'],
            "logo" => _BASEURL . $firm['logo'],
            "islogo" => strlen($firm['logo']) > 0,
            "stamp" => _BASEURL . $firm['stamp'],
            "isstamp" => strlen($firm['stamp']) > 0,
            "sign" => _BASEURL . $firm['sign'],
            "issign" => strlen($firm['sign']) > 0,
            "isfirm" => strlen($firm["firm_name"]) > 0,
            "iscontract" => $this->headerdata["contract_id"] > 0,
            "phone" => $this->headerdata["phone"],
            "bank" => $firm["bank"],
            "bankacc" => $firm["bankacc"],
            "isbank" => (strlen($firm["bankacc"]) > 0 && strlen($firm["bank"]) > 0),
            "email" => $this->headerdata["email"],
            "notes" => $this->notes,
            "document_number" => $this->document_number,
            "totalstr" => $totalstr,
            "total" => H::fa($this->amount),
            "payamount" => H::fa($this->payamount),
            "payed" => H::fa($this->payed),
            "paydisc" => H::fa($this->headerdata["paydisc"])
        );
        if ($this->headerdata["contract_id"] > 0) {
            $contract = \App\Entity\Contract::load($this->headerdata["contract_id"]);
            $header['contract'] = $contract->contract_number;
            $header['createdon'] = H::fd($contract->createdon);
        }


        $report = new \App\Report('doc/invoice.tpl');

        $html = $report->generate($header);

        return $html;
    }

    public function Execute() {
        //списываем бонусы
        if ($this->headerdata['paydisc'] > 0) {
            $customer = \App\Entity\Customer::load($this->customer_id);
            if ($customer->discount > 0) {
                return; //процент
            } else {
                $customer->bonus = $customer->bonus - ($this->headerdata['paydisc'] > 0 ? $this->headerdata['paydisc'] : 0);
                $customer->save();
            }
        }

        if ($this->headerdata['payment'] > 0 && $this->payed > 0) {
            \App\Entity\Pay::addPayment($this->document_id, $this->document_date, $this->payed, $this->headerdata['payment'], \App\Entity\Pay::PAY_BASE_OUTCOME);
        }
        return true;
    }

    protected function getNumberTemplate() {
        return 'СФ-000000';
    }

    public function getRelationBased() {
        $list = array();
        $list['GoodsIssue'] = self::getDesc('GoodsIssue');
        $list['Invoice'] = self::getDesc('Invoice');

        return $list;
    }


    protected function getEmailBody() {
        $firm = H::getFirmData(  $this->headerdata["firm_id"],$this->branch_id);


        $header = array();
        $header['customer_name'] = $this->customer_name;
        $header['firm_name'] = $firm["firm_name"];
        $header['number'] = $this->document_number;
        $header['date'] = H::fd($this->document_date);
        $header['amount'] = H::fa($this->amount);


        $report = new \App\Report('emails/invoice.tpl');

        $html = $report->generate($header);

        return $html;
    }

    protected function getEmailSubject() {
        return H::l('emailinvsub', $this->document_number);
    }

    public function supportedExport() {
        return array(self::EX_EXCEL, self::EX_PDF,self::EX_MAIL);
    }
    
    
}
