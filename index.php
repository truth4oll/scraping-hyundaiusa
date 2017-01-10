<?php

/**
 * This script using libraries Guzzle and DiDom
 * Guzzle - http client with useful functions for get content from website https://github.com/guzzle/guzzle
 * DiDom - library for get elements from html https://github.com/imangazaliev/didom
 *
 */


//connect autoload file from composer
require_once  'vendor/autoload.php';

//url of website which must be scraped
$url = 'https://www.hyundaiusa.com/financial-tools/special-offers.aspx';
//variable zip code
$zip = 90210;


//create cookie var
$jar = new \GuzzleHttp\Cookie\CookieJar();

//create Guzzle Client with using cookies. Each query to website will save cookies in $jar
$client = new \GuzzleHttp\Client([
    //not verify ssl certificate
    'verify' => false,
    'cookies' => $jar
]);

//get page for save cookies and get form, they need for for sending form after
$response = $client->request('GET', $url);
$html =$response->getBody()->getContents();

//create dom from html
$document = new \DiDom\Document();
$dom = $document->loadHtml($html);

//find inputs on page
$form = $dom->find('#aspnetForm input');

//save every input with value
foreach ($form as $item) {
    $form_params[$item->attr('name')] = $item->attr('value');
}

//set zip code
$form_params['ctl00$ContentPlaceHolderContent$specialOffersResults$txtZipCode'] = $zip;
//set other form inputs
$form_params['ctl00$ContentPlaceHolderContent$specialOffersResults$ddlVehicle'] = 'ALL';
$form_params['ctl00$ContentPlaceHolderContent$specialOffersResults$hidModel'] = 'ALL';
$form_params['ctl00$ContentPlaceHolderContent$specialOffersResults$hdnPostCode'] = 1;
$form_params['__EVENTARGUMENT'] = '';
$form_params['__EVENTTARGET'] = 'ctl00$ContentPlaceHolderContent$specialOffersResults$lnkBtnFind';

//second query to website with filled form
$response = $client->request('POST', $url, [
    'form_params'=>$form_params
]);
$html =  $response->getBody()->getContents();

//create dom from html
$document = new \DiDom\Document();
$dom = $document->loadHtml($html);

//find vehicle blocks with information
$items = $dom->find('.promo_container');

//do everu block
foreach ($items as $key=>$item) {
    $data = [];

    //get vehicle model
    $vehicle_price = $item->find('.vehicle_price')[0]->text();
    $promo_features = $item->find('.promo_features')[0];
    list($model,$price) = explode('-',$vehicle_price);
    $data['Vehicle'] = $model;


    //in every block we have li
    //we have to process each block li separately
    foreach ($promo_features->find('li') as $li_src) {
        //replace "," in all numbers
        $li = str_replace(',','',$li_src->text());

        //if current li have information with lease starting
        if (strpos($li, 'Lease starting') !== false){
            //get information with regular expression
            preg_match('/Lease starting at (\$\d+)\/month for (\d+) months. (\$\d+) due/',$li,$match_li);
            $data['Lease Payment'] = $match_li[1];
            $data['Lease Term'] = $match_li[2];
            $data['Lease Down'] = $match_li[3];

            //find span in current block li
            $span = $li_src->find('span')[0];
            //clear characters
            $span =  preg_replace('/[()\s+]/','',$span->text());
            list($data['Expiration Start'],$data['Expiration End']) = explode('-',$span);
        }

        //if current li have information with APR
        if (strpos($li, 'APR') !== false){
            preg_match('/Low APR ([\d.]+)% financing for up to (\d+) months/',$li,$match_li);
            $data['APR Rate'] = $match_li[1];
            $data['APR Term'] = $match_li[2];
        }

        //if current li have information with Retail Bonus Cash
        if (strpos($li, 'Retail Bonus Cash') !== false){
            preg_match('/(\$\d+) Retail Bonus/',$li,$match_li);
            $data['Retail Bonus Cash'] = $match_li[1];
        }

        //if current li have information with Valued Owner Coupon
        if (strpos($li, 'Valued Owner Coupon') !== false){
            preg_match('/(\$\d+) Valued Owner Coupon/',$li,$match_li);
            $data['Valued Owner Coupon'] = $match_li[1];
        }

        //if current li have information with Competitive Coupon
        if (strpos($li, 'Competitive Coupon') !== false){
            preg_match('/(\$\d+) Competitive Coupon/',$li,$match_li);
            $data['Competitive Coupon'] = $match_li[1];
        }
    }
    //save to result array
    $result[] = $data;
}


echo '<pre>';
print_r($result);
echo '</pre>';




