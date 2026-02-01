<?php

namespace App\Repositories\Report;

use App\Interfaces\Report\CleanReportRepositoryInterface;
use App\Models\AlbertaProvincialCatalog;
use App\Models\BritishColumbiaProvincialCatalog;
use App\Models\CleanSheet;
use App\Models\CovaDaignosticReportRetailer;
use App\Models\CovaDiagnosticReport;
use App\Models\eposReports;
use App\Models\LpFixedFeeStructure;
use App\Models\LpVariableFeeStructure;
use App\Models\MbllProvincialCatalog;
use App\Models\OcsProvincialCatalog;
use App\Models\Retailer;
use App\Models\RetailerStatement;
use App\Models\IdealDiagnosticReport;
use App\Models\SaskatchewanProvincialCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanReportRepository implements CleanReportRepositoryInterface
{
    public function checkPos($retailerReportSubmission, $retailer_id)
    {
        $posCleanSheet = $retailerReportSubmission->pos . '_clean_report';
        $this->$posCleanSheet($retailerReportSubmission, $retailer_id);

        return;
    }
    private function epos_clean_report($retailerReportSubmission, $retailer_id)
    {
        $eposReports =  eposReports::where('retailerReportSubmission_id', $retailerReportSubmission->id)->get();

        $retailer = Retailer::find($retailer_id);
        $data = [];
        foreach ($eposReports as $eposReport) {
            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeForEpos($eposReport, $retailerReportSubmission);

            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForEpos($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $eposReport);
            } else {
                $cleanSheet->sku = $eposReport->sku;
                $cleanSheet->product_name = $eposReport->name;
                $cleanSheet->category = $eposReport->compliance_category;
                $cleanSheet->brand = $eposReport->brand;
                $cleanSheet->barcode = $eposReport->barcode;
                $cleanSheet->sold = $eposReport->sold;
                $cleanSheet->opening_inventory_units = $eposReport->opening;
                $cleanSheet->closing_inventory_units = $eposReport->closing;
                $cleanSheet->purchased = $eposReport->purchased;
                $cleanSheet->average_price = $eposReport->average_price;
                $cleanSheet->average_cost = $eposReport->average_cost;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->flag = '1';
                $cleanSheet->comments = 'Record Not found in the Master Catalog';

                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function cleanGreenlineReport($retailerReportSubmission, int $retailerId)
    {
        $retailer = $this->getRetailerEntries(
            relation: 'greenlineReports',
            retailerId: $retailerId,
            submission: $retailerReportSubmission
        );

        $cleanRecords = [];

        foreach ($retailer->greenlineReports as $report) {
            $lpVariable = $this->checkFeeForGreenline($report, $retailerReportSubmission);

            if ($lpVariable) {
                $cleanRecords[] = $this->checkMasterCatalogForGreenline(
                    retailer: $retailer,
                    submission: $retailerReportSubmission,
                    cleanSheet: new CleanSheet(),
                    lpVariable: $lpVariable,
                    greenlineReport: $report
                );
                continue;
            }

            // Record not found in master catalog → create flagged record
            $cleanSheet = new CleanSheet();

            $cleanSheet->sku                        = $report->sku;
            $cleanSheet->product_name               = $report->name;
            $cleanSheet->category                   = $report->compliance_category;
            $cleanSheet->brand                      = $report->brand;
            $cleanSheet->barcode                    = $report->barcode;
            $cleanSheet->sold                       = $report->sold;
            $cleanSheet->opening_inventory_units    = $report->opening;
            $cleanSheet->closing_inventory_units    = $report->closing;
            $cleanSheet->purchased                  = $report->purchased;
            $cleanSheet->average_price              = $report->average_price;
            $cleanSheet->average_cost               = trim($report->average_cost ?? '');
            $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
            $cleanSheet->flag                       = '1';
            $cleanSheet->comments                   = 'Record not found in Master Catalog';

            $cleanRecords[] = $cleanSheet->attributesToArray();
        }

        $this->bulkInsert($cleanRecords);
    }

    private function ideal_clean_report($retailerReportSubmission, $retailer_id)
    {
        $idealDaignosticReports =  IdealDiagnosticReport::with('IdealSalesSummaryReport')->where('retailerReportSubmission_id', $retailerReportSubmission->id)->get();
        $retailer = Retailer::find($retailer_id);

        $data = [];
        foreach ($idealDaignosticReports as $idealDaignosticReport) {
            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeforIdeal($idealDaignosticReport, $retailerReportSubmission);

            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForIdeal($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $idealDaignosticReport);
            } else {
                $average_cost = '0';
                if ($idealDaignosticReport->IdealSalesSummaryReport) {
                    $average_cost = $this->averageCostPos($greenlineReport = null, $provincialCatalog = null, (float)trim($idealDaignosticReport->IdealSalesSummaryReport->quantity_purchased, '$'), $idealDaignosticReport->IdealSalesSummaryReport->purchase_amount);
                }
                $cleanSheet->sku = $idealDaignosticReport->sku;
                $cleanSheet->product_name = $idealDaignosticReport->description;
                $cleanSheet->category = "null";
                $cleanSheet->brand = "null";
                $cleanSheet->barcode = "";
                $cleanSheet->sold = $idealDaignosticReport->unit_sold;
                $cleanSheet->opening_inventory_units = $idealDaignosticReport->opening;
                $cleanSheet->closing_inventory_units = $idealDaignosticReport->closing;
                $cleanSheet->purchased = $idealDaignosticReport->purchases;
                $cleanSheet->average_price = (float)trim($idealDaignosticReport->net_sales_ex) / ((float)trim($idealDaignosticReport->unit_sold) != 0 ? (float)trim($idealDaignosticReport->unit_sold) : '1');
                $cleanSheet->average_cost = $average_cost;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->flag = '1';
                $cleanSheet->comments = 'Record Not found in the Master Catalog';

                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function cova_clean_report($retailerReportSubmission, $retailer_id)
    {
        $retailer = $this->getRetailerEntries($relation = 'covaDaignosticReports', $retailer_id, $retailerReportSubmission);

        $data = [];
        foreach ($retailer->covaDaignosticReports as $covaDaignosticReport) {

            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeforCova($covaDaignosticReport, $retailerReportSubmission);

            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForCova($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $covaDaignosticReport);
            } else {
                $sku = '';
                $average_cost = '';
                if ($retailerReportSubmission->province == 'ON' || $retailerReportSubmission->province == 'Ontario') {
                    $sku = $covaDaignosticReport->ocs_sku;
                } elseif ($retailerReportSubmission->province == 'AB' || $retailerReportSubmission->province == 'Alberta') {
                    $sku = $covaDaignosticReport->aglc_sku;
                } elseif ($retailerReportSubmission->province == 'MB' || $retailerReportSubmission->province == 'Manitoba') {
                    $sku = $covaDaignosticReport->new_brunswick_sku;
                } elseif ($retailerReportSubmission->province == 'BC' || $retailerReportSubmission->province == 'British Columbia') {
                    $sku = $covaDaignosticReport->new_brunswick_sku;
                } elseif ($retailerReportSubmission->province == 'SK' || $retailerReportSubmission->province == 'Saskatchewan') {
                    $sku = $covaDaignosticReport->new_brunswick_sku;
                }

                if ($covaDaignosticReport->CovaSalesSummaryReport) {
                    if ($covaDaignosticReport->CovaSalesSummaryReport->total_Cost && $covaDaignosticReport->CovaSalesSummaryReport->net_qty) {
                        $average_cost = (float)trim($covaDaignosticReport->CovaSalesSummaryReport->total_Cost, '$') / (float)$covaDaignosticReport->CovaSalesSummaryReport->net_qty;
                    }
                }
                $cleanSheet->sku = $sku;
                $cleanSheet->barcode = $covaDaignosticReport->ontario_barcode_upc ? $covaDaignosticReport->ontario_barcode_upc : ($covaDaignosticReport->manitoba_barcode_upc ? $covaDaignosticReport->manitoba_barcode_upc : ($covaDaignosticReport->saskatchewan_barcode_upc ? $covaDaignosticReport->saskatchewan_barcode_upc : ''));
                $cleanSheet->product_name = $covaDaignosticReport->product_name;
                $cleanSheet->category =  $covaDaignosticReport->CovaSalesSummaryReport ? $covaDaignosticReport->CovaSalesSummaryReport->category : '';
                $cleanSheet->brand = 'Null';
                $cleanSheet->sold = $covaDaignosticReport->quantity_sold_units;
                $cleanSheet->opening_inventory_units = $covaDaignosticReport->opening_inventory_units;
                $cleanSheet->closing_inventory_units = $covaDaignosticReport->closing_inventory_units;
                $cleanSheet->purchased = $covaDaignosticReport->quantity_purchased_units;
                $cleanSheet->average_price = $covaDaignosticReport->CovaSalesSummaryReport ? $covaDaignosticReport->CovaSalesSummaryReport->average_retail_price : '$0.00';
                $cleanSheet->average_cost = $average_cost;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->flag = '1';
                $cleanSheet->comments = 'Record Not found in the Master Catalog';

                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function pennylane_clean_report($retailerReportSubmission, $retailer_id)
    {
        $retailer = $this->getRetailerEntries($relation = 'pennylaneReports', $retailer_id, $retailerReportSubmission);

        $data = [];
        foreach ($retailer->pennylaneReports as $pennylaneReports) {
            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeForPennylane($pennylaneReports, $retailerReportSubmission);

            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForPennyLane($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $pennylaneReports);
            } else {
                $average_cost = '';
                if (trim($pennylaneReports->opening_inventory_units) != '0' && $pennylaneReports->opening_inventory_units != null) {
                    $average_cost = (float)(trim($pennylaneReports->opening_inventory_value, '$')) / (float)(trim($pennylaneReports->opening_inventory_units));
                }
                $cleanSheet->sku = $pennylaneReports->product_sku;
                $cleanSheet->product_name = $pennylaneReports->description;
                $cleanSheet->category = $pennylaneReports->category;
                $cleanSheet->brand = "";
                $cleanSheet->barcode = "";
                $cleanSheet->sold = $pennylaneReports->quantity_sold_units;
                $cleanSheet->opening_inventory_units = $pennylaneReports->opening_inventory_units;
                $cleanSheet->closing_inventory_units = $pennylaneReports->closing_inventory_units;
                $cleanSheet->purchased = $pennylaneReports->quantity_purchased_units;
                $cleanSheet->average_price = '0';
                $cleanSheet->average_cost = $average_cost;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->flag = '1';
                $cleanSheet->comments = 'Record Not found in the Master Catalog';

                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function techpos_clean_report($retailerReportSubmission, $retailer_id)
    {
        $retailer = $this->getRetailerEntries($relation = 'techposReports', $retailer_id, $retailerReportSubmission);

        $data = [];
        foreach ($retailer->techposReports as $techposReport) {
            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeForTechpos($techposReport, $retailerReportSubmission);

            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForTechpos($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $techposReport);
            } else {
                $cleanSheet->sku = $techposReport->sku;
                $cleanSheet->product_name = $techposReport->productname;
                $cleanSheet->category = $techposReport->category;
                $cleanSheet->brand = $techposReport->brand;
                $cleanSheet->barcode =  "";
                $cleanSheet->opening_inventory_units = $techposReport->openinventoryunits;
                $cleanSheet->closing_inventory_units = $techposReport->closinginventoryunits;
                $cleanSheet->sold = ((float)$techposReport->openinventoryunits + (float)$techposReport->quantitypurchasedunits) - (float)$techposReport->closinginventoryunits;
                $cleanSheet->purchased = $techposReport->quantitypurchasedunits;
                $cleanSheet->average_price = $this->techposAveragePrice($techposReport);
                $cleanSheet->average_cost = $techposReport->costperunit;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->flag = '1';
                $cleanSheet->comments = 'Record Not found in the Master Catalog';
                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function profittech_clean_report($retailerReportSubmission, $retailer_id)
    {
        $retailer = $this->getRetailerEntries($relation = 'profittechReports', $retailer_id, $retailerReportSubmission);

        $data = [];
        foreach ($retailer->profittechReports as $profittechReports) {
            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeForProfitech($profittechReports, $retailerReportSubmission);

            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForProfitTech($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $profittechReports);
            } else {
                $value = '';
                $average_cost = '';
                if (trim($profittechReports->opening_inventory_units != '0')) {
                    $value = trim($profittechReports->opening_inventory_units);
                } else {
                    $value = 1;
                }

                if ($profittechReports->quantity_purchased_units != '0') {
                    $average_cost = (float) $profittechReports->quantity_purchased_value / (float) $profittechReports->quantity_purchased_units;
                }
                $cleanSheet->sku = $profittechReports->product_sku;
                $cleanSheet->product_name = '';
                $cleanSheet->category = '';
                $cleanSheet->brand = '';
                $cleanSheet->barcode =  '';
                $cleanSheet->sold = $profittechReports->quantity_sold_instore_units;
                $cleanSheet->opening_inventory_units = $profittechReports->opening_inventory_units;
                $cleanSheet->closing_inventory_units = $profittechReports->closing_inventory_units;
                $cleanSheet->purchased = $profittechReports->quantity_purchased_units;
                $cleanSheet->average_price = (float)$profittechReports->opening_inventory_value / (float)$value;
                $cleanSheet->average_cost = $average_cost;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->flag = '1';
                $cleanSheet->comments = 'Record Not found in the Master Catalog';

                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function gobatell_clean_report($retailerReportSubmission, $retailer_id)
    {
        $retailer = $this->getRetailerEntries($relation = 'gobatellDiagnosticReports', $retailer_id, $retailerReportSubmission);

        $data = [];
        foreach ($retailer->gobatellDiagnosticReports as $gobatellDiagnosticReport) {
            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeForGobatell($gobatellDiagnosticReport, $retailerReportSubmission);
            Log::info($lpVariable);
            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForGobatell($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $gobatellDiagnosticReport);
            } else {

                $average_cost = '';
                $average_price = '';
                if ($gobatellDiagnosticReport->GobatellSalesSummaryReport) {
                    if ($gobatellDiagnosticReport->GobatellSalesSummaryReport->purchases_from_suppliers_value && $gobatellDiagnosticReport->GobatellSalesSummaryReport->purchases_from_suppliers_additions != '0') {
                        $average_cost = (float)$gobatellDiagnosticReport->GobatellSalesSummaryReport->purchases_from_suppliers_value / (float)$gobatellDiagnosticReport->GobatellSalesSummaryReport->purchases_from_suppliers_additions;
                    }
                    if ($gobatellDiagnosticReport->GobatellSalesSummaryReport->sales_reductions && $gobatellDiagnosticReport->GobatellSalesSummaryReport->sold_retail_value) {
                        $average_price = (float)$gobatellDiagnosticReport->GobatellSalesSummaryReport->sold_retail_value / (float)($gobatellDiagnosticReport->GobatellSalesSummaryReport->sales_reductions != '0' ? $gobatellDiagnosticReport->GobatellSalesSummaryReport->sales_reductions : '1');
                    }
                }
                $cleanSheet->sku = $gobatellDiagnosticReport->supplier_sku;
                $cleanSheet->product_name = $gobatellDiagnosticReport->product;
                $cleanSheet->category = '';
                $cleanSheet->brand = '';
                $cleanSheet->barcode = $gobatellDiagnosticReport->compliance_code;
                $cleanSheet->sold = $gobatellDiagnosticReport->sales_reductions ? $gobatellDiagnosticReport->sales_reductions : '0';
                $cleanSheet->opening_inventory_units = $gobatellDiagnosticReport->GobatellSalesSummaryReport ? $gobatellDiagnosticReport->GobatellSalesSummaryReport->opening_inventory : '';
                $cleanSheet->closing_inventory_units = $gobatellDiagnosticReport->GobatellSalesSummaryReport ? $gobatellDiagnosticReport->GobatellSalesSummaryReport->closing_inventory : '';
                $cleanSheet->purchased = $gobatellDiagnosticReport->purchases_from_suppliers_additions ? $gobatellDiagnosticReport->purchases_from_suppliers_additions : '0';
                $cleanSheet->average_price = $average_price;
                $cleanSheet->average_cost = '$' . $average_cost;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->flag = '1';
                $cleanSheet->comments = 'Record Not found in the Master Catalog';

                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function ductie_clean_report($retailerReportSubmission, $retailer_id)
    {
        $retailer = $this->getRetailerEntries($relation = 'DuctieDaignosticReports', $retailer_id, $retailerReportSubmission);

        $data = [];
        foreach ($retailer->DuctieDaignosticReports as $DuctieDaignosticReports) {
            $cleanSheet = new CleanSheet();
            $lpVariable = $this->checkFeeForDuchtie($DuctieDaignosticReports, $retailerReportSubmission);

            if ($lpVariable) {
                $data[] = $this->CheckMasterCatalogForDuctie($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $DuctieDaignosticReports);
            } else {
                [$provincialCatalog, $sku, $product_name, $category, $brand, $barcode, $average_cost, $comments, $flag, $average_price] = ['', '', '', '', '', '', '', '', '0', ''];

                if ($retailerReportSubmission->province == 'ON' || $retailerReportSubmission->province == 'Ontario') {
                    $ocsProvincialCatalog = new OcsProvincialCatalog();
                    $provincialCatalog = $this->getProvincialCatalog($ocsProvincialCatalog, $Sku = 'ocs_variant_number', $Barcode = 'gtin', $Product_name = 'product_name', $DuctieDaignosticReports->provincial_sku, $DuctieDaignosticReports->upcgtin, $DuctieDaignosticReports->product, $comments);

                    if ($provincialCatalog == null) {
                        $this->ductieVariableIntialize($DuctieDaignosticReports, $flag, $sku, $product_name, $category, $brand, $barcode, $average_price, $average_cost);
                    } else {
                        $sku = $provincialCatalog->ocs_variant_number ? $provincialCatalog->ocs_variant_number : $DuctieDaignosticReports->provincial_sku;
                        $product_name = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->product : $provincialCatalog->product_name;
                        $category = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->mastercategory : $provincialCatalog->category;
                        $brand = $provincialCatalog->brand;
                        $barcode = $provincialCatalog->gtin ? $provincialCatalog->gtin : $DuctieDaignosticReports->upcgtin;
                        $average_cost = '$' . $DuctieDaignosticReports->unit_cost ? $DuctieDaignosticReports->unit_cost : $provincialCatalog->unit_price;
                        $average_price = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->avgpriceperunit : '0';
                    }
                } elseif ($retailerReportSubmission->province == 'MB' || $retailerReportSubmission->province == 'Manitoba') {
                    $ocsProvincialCatalog = new MbllProvincialCatalog();
                    $provincialCatalog = $this->getProvincialCatalog($ocsProvincialCatalog, $Sku = 'skumbll_item_number', $Barcode = 'upcgtin', $Product_name = 'description1', $DuctieDaignosticReports->provincial_sku, $DuctieDaignosticReports->upcgtin, $DuctieDaignosticReports->product, $comments);

                    if ($provincialCatalog == null) {
                        $this->ductieVariableIntialize($DuctieDaignosticReports, $flag, $sku, $product_name, $category, $brand, $barcode, $average_price, $average_cost);
                    } else {
                        $sku = $provincialCatalog->skumbll_item_number ? $provincialCatalog->skumbll_item_number : $DuctieDaignosticReports->provincial_sku;
                        $product_name = $DuctieDaignosticReports->DuctieSalesSummaryReport ?  $DuctieDaignosticReports->DuctieSalesSummaryReport->product : $provincialCatalog->description1;
                        $category = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->mastercategory : $provincialCatalog->type;
                        $brand = $provincialCatalog->brand;
                        $barcode = $provincialCatalog->upcgtin ? $provincialCatalog->upcgtin : $DuctieDaignosticReports->upcgtin;
                        $average_cost = '$' . $DuctieDaignosticReports->unit_cost ? $DuctieDaignosticReports->unit_cost : $provincialCatalog->unit_price;
                        $average_price = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->avgpriceperunit : '0';
                    }
                } elseif ($retailerReportSubmission->province == 'BC' || $retailerReportSubmission->province == 'British Columbia') {
                    $ocsProvincialCatalog = new BritishColumbiaProvincialCatalog();
                    $provincialCatalog = $this->getProvincialCatalog($ocsProvincialCatalog, $Sku = 'sku', $Barcode = 'su_code', $Product_name = 'product_name', $DuctieDaignosticReports->provincial_sku, $DuctieDaignosticReports->upcgtin, $DuctieDaignosticReports->product, $comments);

                    if ($provincialCatalog == null) {
                        $this->ductieVariableIntialize($DuctieDaignosticReports, $flag, $sku, $product_name, $category, $brand, $barcode, $average_price, $average_cost);
                    } else {
                        $sku = $provincialCatalog->sku ? $provincialCatalog->sku : $DuctieDaignosticReports->provincial_sku;
                        $product_name = $DuctieDaignosticReports->DuctieSalesSummaryReport ?  $DuctieDaignosticReports->DuctieSalesSummaryReport->product : $provincialCatalog->product_name;
                        $category = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->mastercategory : $provincialCatalog->class;
                        $brand = $provincialCatalog->brand_name;
                        $barcode = $provincialCatalog->su_code ? $provincialCatalog->su_code : $DuctieDaignosticReports->upcgtin;
                        $average_cost = '$' . $DuctieDaignosticReports->unit_cost ? $DuctieDaignosticReports->unit_cost : '0';
                        $average_price = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->avgpriceperunit : '0';
                    }
                } elseif ($retailerReportSubmission->province == 'AB' || $retailerReportSubmission->province == 'Alberta') {
                    $ocsProvincialCatalog = new AlbertaProvincialCatalog();
                    $provincialCatalog = $this->getProvincialCatalog($ocsProvincialCatalog, $Sku = 'aglc_sku', $Barcode = 'gtin', $Product_name = 'product_name', $DuctieDaignosticReports->provincial_sku, $DuctieDaignosticReports->upcgtin, $DuctieDaignosticReports->product, $comments);

                    if ($provincialCatalog == null) {
                        $this->ductieVariableIntialize($DuctieDaignosticReports, $flag, $sku, $product_name, $category, $brand, $barcode, $average_price, $average_cost);
                    } else {
                        $sku = $provincialCatalog->aglc_sku ? $provincialCatalog->aglc_sku : $DuctieDaignosticReports->provincial_sku;
                        $product_name = $DuctieDaignosticReports->DuctieSalesSummaryReport ?  $DuctieDaignosticReports->DuctieSalesSummaryReport->product : $provincialCatalog->product_name;
                        $category = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->mastercategory : $provincialCatalog->format;
                        $brand = $provincialCatalog->brand_name;
                        $barcode = $provincialCatalog->gtin ? $provincialCatalog->gtin : $DuctieDaignosticReports->upcgtin;
                        $average_cost = '$' . $DuctieDaignosticReports->unit_cost ? $DuctieDaignosticReports->unit_cost : $provincialCatalog->sell_price_per_unit;
                        $average_price = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->avgpriceperunit :  $provincialCatalog->msrp;;
                    }
                } elseif ($retailerReportSubmission->province == 'SK' || $retailerReportSubmission->province == 'Saskatchewan') {
                    $ocsProvincialCatalog = new SaskatchewanProvincialCatalog();
                    $provincialCatalog = $this->getProvincialCatalog($ocsProvincialCatalog, $Sku = 'sku', $Barcode = 'gtin', $Product_name = 'product_name', $DuctieDaignosticReports->provincial_sku, $DuctieDaignosticReports->upcgtin, $DuctieDaignosticReports->product, $comments);

                    if ($provincialCatalog == null) {
                        $this->ductieVariableIntialize($DuctieDaignosticReports, $flag, $sku, $product_name, $category, $brand, $barcode, $average_price, $average_cost);
                    } else {
                        $sku = $provincialCatalog->sku ? $provincialCatalog->sku : $DuctieDaignosticReports->provincial_sku;
                        $product_name =  $DuctieDaignosticReports->DuctieSalesSummaryReport ?  $DuctieDaignosticReports->DuctieSalesSummaryReport->product  : $provincialCatalog->product_name;
                        $category = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->mastercategory : $provincialCatalog->type;
                        $brand = $provincialCatalog->brand;
                        $barcode = $provincialCatalog->gtin ? $provincialCatalog->gtin : $DuctieDaignosticReports->upcgtin;
                        $average_cost = '$' . $DuctieDaignosticReports->unit_cost ? $DuctieDaignosticReports->unit_cost : $provincialCatalog->per_unit_cost;
                        $average_price = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->avgpriceperunit : '0';
                    }
                }

                $cleanSheet->sku = $sku;
                $cleanSheet->product_name = $product_name;
                $cleanSheet->category = $category;
                $cleanSheet->brand = $brand;
                $cleanSheet->barcode = $barcode;
                $cleanSheet->sold = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->quantitysold : '0';
                $cleanSheet->opening_inventory_units = '';
                $cleanSheet->closing_inventory_units = '';
                $cleanSheet->purchased = $DuctieDaignosticReports->quantitypurchased ? $DuctieDaignosticReports->quantitypurchased : '0';
                $cleanSheet->average_price = $average_price;
                $cleanSheet->average_cost = '$' . $average_cost;
                $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
                $cleanSheet->comments = $comments;
                $cleanSheet->flag = $flag;
                $data[] = $cleanSheet->attributesToArray();
            }
        }
        $this->bulkInsert($data);
        return;
    }

    private function greenlineVariableIntialize($greenlineReport, &$flag, &$sku, &$product_name, &$category, &$brand, &$barcode, &$average_price, &$average_cost)
    {
        $flag = '1';
        $sku = $greenlineReport->sku;
        $product_name = $greenlineReport->name;
        $category = $greenlineReport->compliance_category;
        $brand = $greenlineReport->brand;
        $barcode = $greenlineReport->barcode;
        $average_price = $greenlineReport->average_price;
        $average_cost = $greenlineReport->average_cost;

        return;
    }

    private function covaVariableIntialize($covaDaignosticReport, &$flag, &$sku, &$product_name, &$category, &$brand, &$barcode, &$average_price, &$average_cost)
    {
        $sku = $covaDaignosticReport->ocs_sku ? $covaDaignosticReport->ocs_sku : ($covaDaignosticReport->ylc_sku ? $covaDaignosticReport->ylc_sku : ($covaDaignosticReport->aglc_sku ? $covaDaignosticReport->aglc_sku : ($covaDaignosticReport->new_brunswick_sku ? $covaDaignosticReport->new_brunswick_sku : '')));
        $product_name = $covaDaignosticReport->product_name;
        $category = $covaDaignosticReport->CovaSalesSummaryReport ? $covaDaignosticReport->CovaSalesSummaryReport->category : '';
        $barcode = $covaDaignosticReport->ontario_barcode_upc;
        $average_price = $covaDaignosticReport->CovaSalesSummaryReport ? $covaDaignosticReport->CovaSalesSummaryReport->average_retail_price : '$0.00';
        $average_cost = '$' . $average_cost;
        $flag = '1';

        return;
    }

    private function IdealVariableIntialize($idealDaignosticReport, &$flag, &$sku, &$product_name, &$average_price, &$average_cost)
    {
        $sku = $idealDaignosticReport->sku;
        $product_name = $idealDaignosticReport->description;
        $average_price = '$' . $average_price;
        $average_cost = '$' . $average_cost;
        $flag = '1';

        return;
    }


    private function pennylaneVariableIntialize($pennylaneReports, &$flag, &$sku, &$product_name, &$category, &$brand, &$barcode, &$average_price, &$average_cost)
    {
        $sku = $pennylaneReports->product_sku;
        $product_name = $pennylaneReports->description;
        $category = $pennylaneReports->category;
        $average_price = '0';
        $average_cost = $average_cost ? $average_cost : '0';
        $flag = '1';

        return;
    }

    private function techposVariableIntialize($techposReport, &$flag, &$sku, &$product_name, &$category, &$brand, &$barcode, &$average_price, &$average_cost, $value)
    {
        $flag = '1';
        $sku = $techposReport->sku;
        $average_price = $techposReport->closinginventoryvalue / $value ?? '$0.00';
        $average_cost = $average_cost ? $average_cost : '0';

        return;
    }

    private function profitechVariableIntialize($profittechReports, &$flag, &$sku, &$product_name, &$category, &$brand, &$barcode, &$average_price, &$average_cost, $value)
    {
        $flag = '1';
        $sku = $profittechReports->product_sku;
        $average_price = (float)$profittechReports->opening_inventory_value / ((int)$value != 0 ? $value : '1');
        $average_cost = '$' . ($average_cost ? $average_cost : '0.00');

        return;
    }

    private function globaltellVariableIntialize($gobatellDiagnosticReport, &$flag, &$sku, &$product_name, &$category, &$brand, &$barcode, &$average_price, &$average_cost)
    {
        $sku = $gobatellDiagnosticReport->supplier_sku;
        $product_name = $gobatellDiagnosticReport->product;
        $category = 'Null';
        $brand = 'Null';
        $barcode = $gobatellDiagnosticReport->compliance_code;
        $average_cost = $average_cost ? $average_cost : '0.00';
        $average_price = $average_price ? $average_price : '$0.00';
        $flag = '1';

        return;
    }

    private function ductieVariableIntialize($DuctieDaignosticReports, &$flag, &$sku, &$product_name, &$category, &$brand, &$barcode, &$average_price, &$average_cost)
    {
        $sku = $DuctieDaignosticReports->provincial_sku;
        $product_name = $DuctieDaignosticReports->product;
        $barcode = $DuctieDaignosticReports->upcgtin;
        $category = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->mastercategory : '';
        $average_cost = '$' . $DuctieDaignosticReports->unit_cost;
        $average_price = '0';
        $flag = '1';

        return;
    }

    private function getProvincialCatalog($ocsProvincialCatalog, $sku, $barcode, $product_name, $checkSku, $checkBarcode, $checkProductName, &$comments)
    {
        if ($checkSku != null) {
            $provincialCatalog = $ocsProvincialCatalog->where($sku, $checkSku)->first();
        }
        if ($provincialCatalog == null && $checkSku == null) {
            $comments = 'Sku not found in the Provincial Catalog';
            $provincialCatalog = $ocsProvincialCatalog->where($barcode, $checkBarcode)->first();
        }
        if ($provincialCatalog == null && $checkSku == null && $checkBarcode == null) {
            $comments = $comments . ', Barcode not found in the Provincial Catalog';
            $provincialCatalog = $ocsProvincialCatalog->where($product_name, $checkProductName)->first();
        }

        return $provincialCatalog;
    }

    private function averageCostPos($greenlineReport, $provincialCatalogAverageCost, $total_cost, $net_qty)
    {
        if ($greenlineReport != null) {
            if (trim($greenlineReport->average_cost) != '$0.00') {
                $average_cost = $greenlineReport->average_cost;
            } else {
                $average_cost = '$' . ($provincialCatalogAverageCost ? $provincialCatalogAverageCost : '0.00');
            }
        } elseif ($total_cost != null && $net_qty != null && trim($net_qty) != '0') {
            $average_cost = $total_cost / $net_qty;
        }
        return $average_cost;
    }


    private function getProvincialCatalogIdeal($ocsProvincialCatalog, $sku, $product_name, $idealDaignosticReport, &$comments)
    {
        $provincialCatalog = $ocsProvincialCatalog->where($sku, $idealDaignosticReport->sku)
            ->first();
        if ($provincialCatalog == null) {
            $comments = 'Sku not found in the Provincial Catalog';
        }
        if ($provincialCatalog == null && $idealDaignosticReport->sku != null) {
            $comments = $comments . ', Barcode not found in the Provincial Catalog';
            $provincialCatalog = $ocsProvincialCatalog->where($product_name, $idealDaignosticReport->description)->first();
        }
        return $provincialCatalog;
    }

    private function getProvincialCatalogCova($retailerReportSubmission, $ocsProvincialCatalog, $sku, $barcode, $product_name, $covaDaignosticReport, &$comments)
    {
        $provincialCatalog = $ocsProvincialCatalog->where(function ($q) use ($covaDaignosticReport, $retailerReportSubmission, $sku) {
            if ($retailerReportSubmission->province == 'ON' || $retailerReportSubmission->province == 'Ontario') {
                return $q->where($sku, $covaDaignosticReport->ocs_sku);
            } elseif ($retailerReportSubmission->province == 'AB' || $retailerReportSubmission->province == 'Alberta') {
                return $q->where($sku, $covaDaignosticReport->aglc_sku);
            } else {
                return $q->where($sku, $covaDaignosticReport->new_brunswick_sku);
            }
        })->first();

        // $provincialCatalog = $ocsProvincialCatalog->where($sku, $covaDaignosticReport->aglc_sku)
        //     ->orWhere($sku, $covaDaignosticReport->new_brunswick_sku)
        //     ->orWhere($sku, $covaDaignosticReport->ocs_sku)
        //     ->orWhere($sku, $covaDaignosticReport->ylc_sku)->first();

        if ($provincialCatalog == null) {
            $comments = 'Sku not found in the Provincial Catalog';
            $provincialCatalog = $ocsProvincialCatalog->where($barcode, $covaDaignosticReport->manitoba_barcode_upc)
                ->orWhere($barcode, $covaDaignosticReport->ontario_barcode_upc)
                ->orWhere($barcode, $covaDaignosticReport->saskatchewan_barcode_upc)->first();
        }
        if ($provincialCatalog == null) {
            $comments = $comments . ', Barcode not found in the Provincial Catalog';
            $provincialCatalog = $ocsProvincialCatalog->where($product_name, $covaDaignosticReport->product_name)->first();
        }

        return $provincialCatalog;
    }

    private function getRetailerEntries($relation, $retailer_id, $retailerReportSubmission)
    {
        if ($relation == 'DuctieDaignosticReports') {
            $retailer = Retailer::where('id', $retailer_id)->with(['DuctieDaignosticReports' => function ($q) use ($retailerReportSubmission) {
                $q->whereMonth('date', Carbon::parse($retailerReportSubmission->date)->format('m'));
                $q->whereYear('date', Carbon::parse($retailerReportSubmission->date)->format('Y'));
                $q->where('province', $retailerReportSubmission->province);
                $q->where('ductie_diagnostic_report_retailers.location', $retailerReportSubmission->location);
                return $q;
            }])->first();
        } elseif ($relation == 'idealDaignosticReports') {
            $retailer = Retailer::where('id', $retailer_id)->with([$relation => function ($q) use ($retailerReportSubmission) {
                $q->whereMonth('date', Carbon::parse($retailerReportSubmission->date)->format('m'));
                $q->whereYear('date', Carbon::parse($retailerReportSubmission->date)->format('Y'));
                return $q;
            }])->first();
        } else {
            $retailer = Retailer::where('id', $retailer_id)->with([$relation => function ($q) use ($retailerReportSubmission) {
                $q->whereMonth('date', Carbon::parse($retailerReportSubmission->date)->format('m'));
                $q->whereYear('date', Carbon::parse($retailerReportSubmission->date)->format('Y'));
                $q->where('province', $retailerReportSubmission->province);
                $q->where('location', $retailerReportSubmission->location);
                return $q;
            }])->first();
        }
        return $retailer;
    }

    public function checkRetailerStatement($retailerReportSubmission, $retailer_id)
    {
        $province_name = '';
        $province_id = '';

        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        $cleanSheets = CleanSheet::where('retailerReportSubmission_id', $retailerReportSubmission->id)->get();
        $retailer = Retailer::where('id', $retailer_id)->first();

        $data = [];
        foreach ($cleanSheets as $cleanSheet) {
            $lpVariable = '';
            if ((int) $cleanSheet->purchased > 0) {
                if ($cleanSheet->sku != null) {
                    $lpVariable = LpVariableFeeStructure::where(function ($q) use ($retailerReportSubmission) {
                        return $q->whereMonth('created_at', Carbon::parse($retailerReportSubmission->created_at)->format('m'))->whereYear('created_at', Carbon::parse($retailerReportSubmission->created_at)->format('Y'));
                    })->where(function ($q) use ($cleanSheet) {
                        return $q->where('provincial', $cleanSheet->sku);
                    })->where(function ($q) use ($retailerReportSubmission, $province_id, $province_name) {
                        return $q->where('province', $province_id)->orWhere('province', $province_name);
                    })->with('lps')->first();
                }
                if ($lpVariable == null && $cleanSheet->sku == null) {
                    $lpVariable = LpVariableFeeStructure::where(function ($q) use ($retailerReportSubmission) {
                        return $q->whereMonth('created_at', Carbon::parse($retailerReportSubmission->created_at)->format('m'))->whereYear('created_at', Carbon::parse($retailerReportSubmission->created_at)->format('Y'));
                    })->where(function ($q) use ($cleanSheet) {
                        return $q->where('GTin', $cleanSheet->barcode);
                    })->where(function ($q) use ($retailerReportSubmission, $province_id, $province_name) {
                        return $q->where('province', $province_id)->orWhere('province', $province_name);
                    })->with('lps')->first();
                }

                if ($lpVariable == null && $cleanSheet->sku == null && $cleanSheet->barcode == null) {
                    $lpVariable = LpVariableFeeStructure::where(function ($q) use ($retailerReportSubmission) {
                        return $q->whereMonth('created_at', Carbon::parse($retailerReportSubmission->created_at)->format('m'))->whereYear('created_at', Carbon::parse($retailerReportSubmission->created_at)->format('Y'));
                    })->where(function ($q) use ($cleanSheet) {
                        return $q->where('product_name', $cleanSheet->product_name);
                    })->where(function ($q) use ($retailerReportSubmission, $province_id, $province_name) {
                        return $q->where('province', $province_id)->orWhere('province', $province_name);
                    })->with('lps')->first();
                }

                if ($lpVariable) {
                    $retailerStatment = $this->lpVariableFeeAssign($retailerReportSubmission, $lpVariable, $cleanSheet, $retailer);
                    $data[] = $retailerStatment->attributesToArray();
                }
            }
        }

        foreach (array_chunk($data, 50) as $d) {
            DB::table('retailer_statements')->insert($d);
        }
        return;
    }

    private function lpVariableFeeAssign($retailerReportSubmission, $lpVariable, $cleanSheet, $retailer)
    {
        $retailerStatment = new RetailerStatement;

        $retailerStatment->lp = $lpVariable->lps ? $lpVariable->lps->user->name : $lpVariable->lp;
        $retailerStatment->product = $lpVariable->product_name ? $lpVariable->product_name : $cleanSheet->product_name;
        $retailerStatment->sku = $lpVariable->provincial ? $lpVariable->provincial : $cleanSheet->sku;
        $retailerStatment->barcode = $lpVariable->GTin ? $lpVariable->GTin : $cleanSheet->barcode;
        $retailerStatment->quantity = (int)$cleanSheet->purchased ? $cleanSheet->purchased : 0;
        $retailerStatment->unit_cost = $lpVariable->unit_cost ? trim($lpVariable->unit_cost, '$') : trim($cleanSheet->average_cost, '$');
        $retailerStatment->total_purchase_cost = (float) $retailerStatment->quantity * (float)$retailerStatment->unit_cost;
        $retailerStatment->fee_per = (float)trim($lpVariable->data, '%') * 100;

        $retailerStatment->fee_in_dollar
            = (float)$retailerStatment->total_purchase_cost * $retailerStatment->fee_per / 100;
        $retailerStatment->ircc_per = '20';
        $retailerStatment->ircc_dollar
            = $retailerStatment->fee_in_dollar * (int)$retailerStatment->ircc_per / 100;
        $retailerStatment->total_fee = $retailerStatment->fee_in_dollar - $retailerStatment->ircc_dollar;
        $retailerStatment->quantity_sold = $cleanSheet->sold;
        $retailerStatment->average_price = $cleanSheet->average_price;
        $retailerStatment->opening_inventory_units = $cleanSheet->opening_inventory_units;
        $retailerStatment->closing_inventory_units = $cleanSheet->closing_inventory_units;
        $retailerStatment->category = $lpVariable->category;
        $retailerStatment->brand = $cleanSheet->brand;

        $retailerStatment->lp_id = $lpVariable->lp_id;
        $retailerStatment->retailerReportSubmission_id = $retailerReportSubmission->id;

        return $retailerStatment;
    }

    private function getRetailerProvince($retailerReportSubmission, &$province_id, &$province_name)
    {
        if ($retailerReportSubmission->province == 'ON' || $retailerReportSubmission->province == 'Ontario') {
            $province_name = 'Ontario';
            $province_id = 'ON';
        } elseif ($retailerReportSubmission->province == 'MB' || $retailerReportSubmission->province == 'Manitoba') {
            $province_name = 'Manitoba';
            $province_id = 'MB';
        } elseif ($retailerReportSubmission->province == 'BC' || $retailerReportSubmission->province == 'British Columbia') {
            $province_name = 'British Columbia';
            $province_id = 'BC';
        } elseif ($retailerReportSubmission->province == 'AB' || $retailerReportSubmission->province == 'Alberta') {
            $province_name = 'Alberta';
            $province_id = 'AB';
        } elseif ($retailerReportSubmission->province == 'SK' || $retailerReportSubmission->province == 'Saskatchewan') {
            $province_name = 'Saskatchewan';
            $province_id = 'SK';
        }

        return;
    }
    private function CheckMasterCatalogForEpos($retailer, $retailerReportSubmission, &$cleanSheet, $lpVariable, $greenlineReport)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $greenlineReport->sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $greenlineReport->name;
        $cleanSheet->category = $lpVariable->category ? $lpVariable->category : $greenlineReport->compliance_category;
        $cleanSheet->brand = $lpVariable->brand ? $lpVariable->brand : $greenlineReport->brand;
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : $greenlineReport->barcode;
        $cleanSheet->sold = $greenlineReport->sold;
        $cleanSheet->opening_inventory_units = $greenlineReport->opening;
        $cleanSheet->closing_inventory_units = $greenlineReport->closing;
        $cleanSheet->purchased = $greenlineReport->purchased;
        $cleanSheet->average_price = $greenlineReport->average_price;
        $cleanSheet->average_cost = $lpVariable->unit_cost ? $lpVariable->unit_cost : $greenlineReport->average_cost;
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForGreenline($retailer, $retailerReportSubmission, &$cleanSheet, $lpVariable, $greenlineReport)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $greenlineReport->sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $greenlineReport->name;
        $cleanSheet->category = $lpVariable->category ? $lpVariable->category : $greenlineReport->compliance_category;
        $cleanSheet->brand = $lpVariable->brand ? $lpVariable->brand : $greenlineReport->brand;
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : $greenlineReport->barcode;
        $cleanSheet->sold = $greenlineReport->sold;
        $cleanSheet->opening_inventory_units = $greenlineReport->opening;
        $cleanSheet->closing_inventory_units = $greenlineReport->closing;
        $cleanSheet->purchased = $greenlineReport->purchased;
        $cleanSheet->average_price = $greenlineReport->average_price;
        $cleanSheet->average_cost = $lpVariable->unit_cost ? $lpVariable->unit_cost : $greenlineReport->average_cost;
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForPennylane($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $pennylaneReports)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $pennylaneReports->product_sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $pennylaneReports->description;
        $cleanSheet->category = $lpVariable->category ? $lpVariable->category : $pennylaneReports->category;
        $cleanSheet->brand = $lpVariable->brand ? $lpVariable->brand : '';
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : '';
        $cleanSheet->sold = $pennylaneReports->quantity_sold_units;
        $cleanSheet->opening_inventory_units = $pennylaneReports->opening_inventory_units;
        $cleanSheet->closing_inventory_units = $pennylaneReports->closing_inventory_units;
        $cleanSheet->purchased = $pennylaneReports->quantity_purchased_units;
        $cleanSheet->average_price = '';
        $cleanSheet->average_cost = $lpVariable->unit_cost;
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForTechpos($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $techposReport)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $techposReport->sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $techposReport->productname;
        $cleanSheet->category = $lpVariable->category ? $lpVariable->category : $techposReport->category;
        $cleanSheet->brand = $lpVariable->brand ? $lpVariable->brand : $techposReport->brand;
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : '';
        $cleanSheet->opening_inventory_units = $techposReport->openinventoryunits;
        $cleanSheet->closing_inventory_units = $techposReport->closinginventoryunits;
        $cleanSheet->sold = ((float)$techposReport->openinventoryunits + (float)$techposReport->quantitypurchasedunits) - (float)$techposReport->closinginventoryunits;
        $cleanSheet->purchased = $techposReport->quantitypurchasedunits;
        $cleanSheet->average_price = $this->techposAveragePrice($techposReport);
        $cleanSheet->average_cost = $lpVariable->unit_cost ? $lpVariable->unit_cost : $techposReport->costperunit;
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForProfitTech($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $profittechReports)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $profittechReports->product_sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : '';
        $cleanSheet->category = $lpVariable->category ? $lpVariable->category : '';
        $cleanSheet->brand = $lpVariable->brand ? $lpVariable->brand : '';
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : '';
        $cleanSheet->sold = $profittechReports->quantity_sold_instore_units;
        $cleanSheet->opening_inventory_units = $profittechReports->opening_inventory_units;
        $cleanSheet->closing_inventory_units = $profittechReports->closing_inventory_units;
        $cleanSheet->purchased = $profittechReports->quantity_purchased_units;
        $cleanSheet->average_price = $this->profictAverageCost($profittechReports);
        $cleanSheet->average_cost = $lpVariable->unit_cost;
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForGobatell($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $gobatellDiagnosticReport)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $gobatellDiagnosticReport->supplier_sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $gobatellDiagnosticReport->product;
        $cleanSheet->category = $lpVariable->category ? $lpVariable->category : '';
        $cleanSheet->brand = $lpVariable->brand ? $lpVariable->brand : '';
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : $gobatellDiagnosticReport->compliance_code;
        $cleanSheet->sold = $gobatellDiagnosticReport->sales_reductions ? $gobatellDiagnosticReport->sales_reductions : '0';
        $cleanSheet->opening_inventory_units = $gobatellDiagnosticReport->GobatellSalesSummaryReport ? $gobatellDiagnosticReport->GobatellSalesSummaryReport->opening_inventory : '';
        $cleanSheet->closing_inventory_units = $gobatellDiagnosticReport->GobatellSalesSummaryReport ? $gobatellDiagnosticReport->GobatellSalesSummaryReport->closing_inventory : '';
        $cleanSheet->purchased = $gobatellDiagnosticReport->purchases_from_suppliers_additions ? $gobatellDiagnosticReport->purchases_from_suppliers_additions : '0';
        $cleanSheet->average_price = $this->averagePriceGlobalTell($gobatellDiagnosticReport);
        $cleanSheet->average_cost = $lpVariable->unit_cost;
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForDuctie($retailer, $retailerReportSubmission, $cleanSheet, $lpVariable, $DuctieDaignosticReports)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $DuctieDaignosticReports->provincial_sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $DuctieDaignosticReports->DuctieSalesSummaryReport->product;
        $cleanSheet->category = $lpVariable->category ? $lpVariable->category : $DuctieDaignosticReports->DuctieSalesSummaryReport->mastercategory;
        $cleanSheet->brand = $lpVariable->brand ? $lpVariable->brand : '';
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : $DuctieDaignosticReports->upcgtin;
        $cleanSheet->sold = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->quantitysold : '0';
        $cleanSheet->opening_inventory_units = '0';
        $cleanSheet->closing_inventory_units = '0';
        $cleanSheet->purchased = $DuctieDaignosticReports->quantitypurchased ? $DuctieDaignosticReports->quantitypurchased : '0';

        $cleanSheet->average_price = $DuctieDaignosticReports->DuctieSalesSummaryReport ? $DuctieDaignosticReports->DuctieSalesSummaryReport->avgpriceperunit : '0';
        $cleanSheet->average_cost = $lpVariable->unit_cost ? $lpVariable->unit_cost : $DuctieDaignosticReports->unit_cost;
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForCova($retailer, $retailerReportSubmission, &$cleanSheet, $lpVariable, $covaDaignosticReport)
    {
        Log::info($lpVariable);
        $sku = '';
        $average_cost = '';
        if ($retailerReportSubmission->province == 'ON' || $retailerReportSubmission->province == 'Ontario') {
            $sku = $covaDaignosticReport->ocs_sku;
        } elseif ($retailerReportSubmission->province == 'AB' || $retailerReportSubmission->province == 'Alberta') {
            $sku = $covaDaignosticReport->aglc_sku;
        } elseif ($retailerReportSubmission->province == 'MB' || $retailerReportSubmission->province == 'Manitoba') {
            $sku = $covaDaignosticReport->new_brunswick_sku;
        } elseif ($retailerReportSubmission->province == 'BC' || $retailerReportSubmission->province == 'British Columbia') {
            $sku = $covaDaignosticReport->new_brunswick_sku;
        } elseif ($retailerReportSubmission->province == 'SK' || $retailerReportSubmission->province == 'Saskatchewan') {
            $sku = $covaDaignosticReport->new_brunswick_sku;
        }
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $covaDaignosticReport->product_name;
        $cleanSheet->category =
            $covaDaignosticReport->CovaSalesSummaryReport ? $covaDaignosticReport->CovaSalesSummaryReport->category : $lpVariable->category;
        $cleanSheet->brand = $lpVariable->brand;
        $cleanSheet->barcode = $lpVariable->GTin ? $lpVariable->GTin : $covaDaignosticReport->manitoba_barcode_upc;
        $cleanSheet->sold = $covaDaignosticReport->quantity_sold_units;
        $cleanSheet->opening_inventory_units = $covaDaignosticReport->opening_inventory_units;
        $cleanSheet->closing_inventory_units =
            $covaDaignosticReport->closing_inventory_units;
        $cleanSheet->purchased =
            $covaDaignosticReport->quantity_purchased_units;
        $cleanSheet->average_price =
            $covaDaignosticReport->CovaSalesSummaryReport ? $covaDaignosticReport->CovaSalesSummaryReport->average_retail_price : '$0.00';
        $cleanSheet->average_cost = $lpVariable->unit_cost ? $lpVariable->unit_cost : $this->averageCostCova($covaDaignosticReport);
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return
            $cleanSheet->attributesToArray();
    }

    private function CheckMasterCatalogForIdeal($retailer, $retailerReportSubmission, &$cleanSheet, $lpVariable, $idealDaignosticReport)
    {
        $cleanSheet->sku = $lpVariable->provincial ? $lpVariable->provincial : $idealDaignosticReport->sku;
        $cleanSheet->product_name = $lpVariable->product_name ? $lpVariable->product_name : $idealDaignosticReport->description;
        $cleanSheet->category = $lpVariable->category ?? "";
        $cleanSheet->brand = $lpVariable->brand ?? "";
        $cleanSheet->barcode = $lpVariable->GTin;
        $cleanSheet->sold =    $idealDaignosticReport->unit_sold ?? "0";
        $cleanSheet->opening_inventory_units = $idealDaignosticReport->opening;
        $cleanSheet->closing_inventory_units = $idealDaignosticReport->closing;
        $cleanSheet->purchased = $idealDaignosticReport->purchases ?? "0";
        $cleanSheet->average_price = $this->averagePriceIdeal($idealDaignosticReport);
        $cleanSheet->average_cost = $lpVariable->unit_cost ? $lpVariable->unit_cost : $this->averageCostIdeal($idealDaignosticReport);
        $cleanSheet->retailerReportSubmission_id = $retailerReportSubmission->id;
        $cleanSheet->flag = '0';
        $cleanSheet->comments = 'Found in the Master Catalog';

        return
            $cleanSheet->attributesToArray();
    }
    private function averagePriceIdeal($idealDaignosticReport)
    {
        $average_price = '';
        if ($idealDaignosticReport->net_sales_ex && $idealDaignosticReport->unit_sold  != '0') {
            $average_price = (float)trim($idealDaignosticReport->net_sales_ex) / (float)trim($idealDaignosticReport->unit_sold);
        } else {
            $average_price = '0.00';
        }

        return $average_price;
    }
    private function averageCostIdeal($idealDaignosticReport)
    {
        $average_cost = '';
        if ($idealDaignosticReport->IdealSalesSummaryReport) {
            if ($idealDaignosticReport->IdealSalesSummaryReport->quantity_purchased && $idealDaignosticReport->IdealSalesSummaryReport->purchase_amount  != '0') {
                $average_cost = $this->averageCostPos($greenlineReport = null, $provincialCatalog = null, (float)trim($idealDaignosticReport->IdealSalesSummaryReport->quantity_purchased, '$'), $idealDaignosticReport->IdealSalesSummaryReport->purchase_amount);
            } else {
                $average_cost = '0.00';
            }
        } else {
            $average_cost = '0.00';
        }
        return $average_cost;
    }

    private function averageCostCova($covaDaignosticReport)
    {
        $average_cost = '';
        if ($covaDaignosticReport->CovaSalesSummaryReport) {
            if ($covaDaignosticReport->CovaSalesSummaryReport->total_Cost && $covaDaignosticReport->CovaSalesSummaryReport->net_qty  != '0') {
                $average_cost = $this->averageCostPos($greenlineReport = null, $provincialCatalog = null, (float)trim($covaDaignosticReport->CovaSalesSummaryReport->total_Cost, '$'), $covaDaignosticReport->CovaSalesSummaryReport->net_qty);
            } else {
                $average_cost = '0.00';
            }
        } else {
            $average_cost = '0.00';
        }
        return $average_cost;
    }

    private function techposAveragePrice($techposReport)
    {
        $value = '';
        if (trim($techposReport->quantitysoldunits) != '0') {
            $value = trim($techposReport->quantitysoldunits);
        } else {
            $value = 1;
        }
        $average_price = (float)$techposReport->quantitysoldvalue / $value ?? '$0.00';

        return $average_price;
    }

    private function profictAverageCost($profittechReports)
    {
        $value = '';
        if (trim($profittechReports->opening_inventory_units != '0')) {
            $value = trim($profittechReports->opening_inventory_units);
        } else {
            $value = 1;
        }

        $average_price = (float)$profittechReports->opening_inventory_value / ((int)$value != 0 ? $value : '1');

        return $average_price;
    }

    private function averagePriceGlobalTell($gobatellDiagnosticReport)
    {
        $average_price = '';
        if ($gobatellDiagnosticReport->GobatellSalesSummaryReport->sales_reductions && $gobatellDiagnosticReport->GobatellSalesSummaryReport->sold_retail_value) {
            $average_price = (float)$gobatellDiagnosticReport->GobatellSalesSummaryReport->sold_retail_value / (float)($gobatellDiagnosticReport->GobatellSalesSummaryReport->sales_reductions != '0' ? $gobatellDiagnosticReport->GobatellSalesSummaryReport->sales_reductions : '1');
        }
        return $average_price;
    }

    private function checkFeeForEpos($greenlineReport, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', ''];
        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($greenlineReport->sku != null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('provincial', $greenlineReport->sku)
                ->Province($province_id, $province_name)->first();
        }
        if ($lpVariable == null && $greenlineReport->sku == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('GTin', $greenlineReport->barcode)
                ->Province($province_id, $province_name)->first();
        }

        if ($lpVariable == null && $greenlineReport->sku == null && $greenlineReport->barcode == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $greenlineReport->name)
                ->Province($province_id, $province_name)->first();
        }

        return $lpVariable;
    }

    private function checkFeeForGreenline($greenlineReport, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', ''];
        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($greenlineReport->sku != null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('provincial', $greenlineReport->sku)
                ->Province($province_id, $province_name)->first();
        }
        if ($lpVariable == null && $greenlineReport->sku == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('GTin', $greenlineReport->barcode)
                ->Province($province_id, $province_name)->first();
        }

        if ($lpVariable == null && $greenlineReport->sku == null && $greenlineReport->barcode == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $greenlineReport->name)
                ->Province($province_id, $province_name)->first();
        }

        return $lpVariable;
    }

    private function checkFeeforCova($covaDaignosticReport, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', ''];
        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($retailerReportSubmission->province == 'ON' || $retailerReportSubmission->province == 'Ontario') {
            if ($covaDaignosticReport->ocs_sku != null) {
                $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where(function ($q) use ($covaDaignosticReport, $retailerReportSubmission) {
                    return $q->where('provincial', $covaDaignosticReport->ocs_sku);
                })->Province($province_id, $province_name)->first();
            }
        } elseif ($retailerReportSubmission->province == 'AB' || $retailerReportSubmission->province == 'Alberta') {
            if ($covaDaignosticReport->aglc_sku != null) {
                $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where(function ($q) use ($covaDaignosticReport, $retailerReportSubmission) {
                    return $q->where('provincial', $covaDaignosticReport->aglc_sku);
                })->Province($province_id, $province_name)->first();
            }
        } elseif ($retailerReportSubmission->province == 'MB' || $retailerReportSubmission->province == 'Manitoba') {
            if ($covaDaignosticReport->new_brunswick_sku != null) {
                $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where(function ($q) use ($covaDaignosticReport, $retailerReportSubmission) {
                    return $q->where('provincial', $covaDaignosticReport->new_brunswick_sku);
                })->Province($province_id, $province_name)->first();
            }
        } elseif ($retailerReportSubmission->province == 'BC' || $retailerReportSubmission->province == 'British Columbia') {
            if ($covaDaignosticReport->new_brunswick_sku != null) {
                $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where(function ($q) use ($covaDaignosticReport, $retailerReportSubmission) {
                    return $q->where('provincial', $covaDaignosticReport->new_brunswick_sku);
                })->Province($province_id, $province_name)->first();
            }
        } elseif ($retailerReportSubmission->province == 'SK' || $retailerReportSubmission->province == 'Saskatchewan') {
            if ($covaDaignosticReport->new_brunswick_sku != null) {
                $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where(function ($q) use ($covaDaignosticReport, $retailerReportSubmission) {
                    return $q->where('provincial', $covaDaignosticReport->new_brunswick_sku);
                })->Province($province_id, $province_name)->first();
            }
        }

        if ($lpVariable == null) {
            if ($retailerReportSubmission->province == 'ON' || $retailerReportSubmission->province == 'Ontario') {
                if ($covaDaignosticReport->ocs_sku == null) {
                    $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)
                        ->where(function ($q) use ($covaDaignosticReport) {
                            return $q->where('GTin', $covaDaignosticReport->ontario_barcode_upc);
                        })->Province($province_id, $province_name)->first();
                }
            } elseif ($retailerReportSubmission->province == 'AB' || $retailerReportSubmission->province == 'Alberta') {
                if ($covaDaignosticReport->aglc_sku == null) {
                    $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)
                        ->where(function ($q) use ($covaDaignosticReport) {
                            return $q->where('GTin', $covaDaignosticReport->manitoba_barcode_upc)
                                ->orWhere('GTin', $covaDaignosticReport->ontario_barcode_upc)
                                ->orWhere('GTin', $covaDaignosticReport->saskatchewan_barcode_upc);
                        })->Province($province_id, $province_name)->first();
                }
            } elseif ($retailerReportSubmission->province == 'MB' || $retailerReportSubmission->province == 'Manitoba') {
                if ($covaDaignosticReport->new_brunswick_sku == null) {
                    $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)
                        ->where(function ($q) use ($covaDaignosticReport) {
                            return $q->where('GTin', $covaDaignosticReport->manitoba_barcode_upc);
                        })->Province($province_id, $province_name)->first();
                }
            } elseif ($retailerReportSubmission->province == 'BC' || $retailerReportSubmission->province == 'British Columbia') {
                if ($covaDaignosticReport->new_brunswick_sku == null) {
                    $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)
                        ->where(function ($q) use ($covaDaignosticReport) {
                            return $q->where('GTin', $covaDaignosticReport->manitoba_barcode_upc)
                                ->orWhere('GTin', $covaDaignosticReport->ontario_barcode_upc)
                                ->orWhere('GTin', $covaDaignosticReport->saskatchewan_barcode_upc);
                        })->Province($province_id, $province_name)->first();
                }
            } elseif ($retailerReportSubmission->province == 'SK' || $retailerReportSubmission->province == 'Saskatchewan') {
                if ($covaDaignosticReport->new_brunswick_sku == null) {
                    $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)
                        ->where(function ($q) use ($covaDaignosticReport) {
                            return $q->where('GTin', $covaDaignosticReport->saskatchewan_barcode_upc);
                        })->Province($province_id, $province_name)->first();
                }
            }
        }

        if ($lpVariable == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $covaDaignosticReport->product_name)
                ->Province($province_id, $province_name)->first();
        }

        return $lpVariable;
    }

    private function checkFeeforIdeal($idealDaignosticReport, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', ''];
        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($idealDaignosticReport->sku != null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where(function ($q) use ($idealDaignosticReport) {
                return $q->where('provincial', $idealDaignosticReport->sku);
            })->Province($province_id, $province_name)->first();
        }

        if ($lpVariable == null && $idealDaignosticReport->sku == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $idealDaignosticReport->description)
                ->Province($province_id, $province_name)->first();
        }
        return $lpVariable;
    }

    private function checkFeeForPennylane($pennylaneReports, $retailerReportSubmission)
    {
        [$province_id, $province_name,  $lpVariable] = ['', '', ''];

        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($pennylaneReports->product_sku != null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('provincial', $pennylaneReports->product_sku)
                ->Province($province_id, $province_name)->first();
        }
        if ($lpVariable == null && $pennylaneReports->product_sku == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $pennylaneReports->description)->Province($province_id, $province_name)->first();
        }

        return $lpVariable;
    }

    private function checkFeeForTechpos($techposReport, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', ''];
        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($techposReport->sku != null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('provincial', $techposReport->sku)
                ->Province($province_id, $province_name)->first();
        }
        if ($lpVariable == null && $techposReport->sku == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $techposReport->productname)
                ->Province($province_id, $province_name)->first();
        }

        return $lpVariable;
    }

    private function checkFeeForProfitech($profittechReports, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', ''];
        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('provincial', $profittechReports->product_sku)
            ->Province($province_id, $province_name)->first();

        return $lpVariable;;
    }

    private function checkFeeForGobatell($gobatellDiagnosticReport, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', ''];

        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($gobatellDiagnosticReport->supplier_sku != null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('provincial', $gobatellDiagnosticReport->supplier_sku)
                ->Province($province_id, $province_name)->first();
        }
        if ($lpVariable == null && $gobatellDiagnosticReport->supplier_sku == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('GTin', $gobatellDiagnosticReport->compliance_code)
                ->Province($province_id, $province_name)->first();
        }

        if ($lpVariable == null && $gobatellDiagnosticReport->supplier_sku == null && $gobatellDiagnosticReport->compliance_code == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $gobatellDiagnosticReport->product)
                ->Province($province_id, $province_name)->first();
        }

        return $lpVariable;
    }

    private function checkFeeForDuchtie($DuctieDaignosticReports, $retailerReportSubmission)
    {
        [$province_id, $province_name, $lpVariable] = ['', '', '', ''];
        $this->getRetailerProvince($retailerReportSubmission, $province_id, $province_name);
        if ($DuctieDaignosticReports->provincial_sku != null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('provincial', $DuctieDaignosticReports->provincial_sku)
                ->Province($province_id, $province_name)->first();
        }
        if ($lpVariable == null && $DuctieDaignosticReports->provincial_sku == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('GTin', $DuctieDaignosticReports->upcgtin)
                ->Province($province_id, $province_name)->first();
        }

        if ($lpVariable == null && $DuctieDaignosticReports->provincial_sku == null && $DuctieDaignosticReports->upcgtin == null) {
            $lpVariable = LpVariableFeeStructure::Date($retailerReportSubmission)->where('product_name', $DuctieDaignosticReports->product)
                ->Province($province_id, $province_name)->first();
        }

        return $lpVariable;
    }

    private function bulkInsert($data)
    {
        foreach (array_chunk($data, 500) as $d) {
            DB::table('clean_sheets')->insert($d);
        }
        return;
    }
}
