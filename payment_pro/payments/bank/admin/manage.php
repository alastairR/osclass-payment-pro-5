<?php if ( ! defined('ABS_PATH')) exit('ABS_PATH is not loaded. Direct access is not allowed.');
require_once PAYMENT_PRO_PATH . "payments/bank/BankDataTable.php";

if(Params::getParam('status')==='' && Params::getParam('tx')==='') {
    Params::setParam('status', PAYMENT_PRO_PENDING);
}
if(Params::getParam('status')==='*') {
    Params::setParam('status', '');
}

$paction = Params::getParam('paction');
$id = Params::getParam('id');
switch($paction) {
    case 'payemail':
        $pay = Params::getParam('pay');

        $data = ModelPaymentPro::newInstance()->getPayment($id);
        $products = payment_pro_invoice_to_items(ModelPaymentPro::newInstance()->itemsByInvoice($id));

        $data['user'] = $data['fk_i_user_id'];

        // NOTIFY USER
        $data['products'] = $products;
        payment_pro_send_bank_notification($data);
        // END NOTIFY

        ob_get_clean();
        if($pay==1) {
            foreach($products as $product) {
                osc_run_hook('payment_pro_item_paid', $product, $data, $id);
            }
            ModelPaymentPro::newInstance()->updatePayment($id, array('i_status' => PAYMENT_PRO_COMPLETED));
            osc_add_flash_ok_message(__('The invoice has been paid and the user has been notified', 'payment_pro'), 'admin');
        } else {
            osc_add_flash_ok_message(__('The user has been notified', 'payment_pro'), 'admin');
        }
        osc_redirect_to(osc_route_admin_url('payment-pro-admin-bank'));
        break;
    case 'pay':
        $pay = Params::getParam('pay');

        $data = ModelPaymentPro::newInstance()->getPayment($id);
        $products = payment_pro_invoice_to_items(ModelPaymentPro::newInstance()->itemsByInvoice($id));

        $data['user'] = $data['fk_i_user_id'];

        ob_get_clean();
        if($pay==1) {
            foreach($products as $product) {
                osc_run_hook('payment_pro_item_paid', $product, $data, $id);
            }
            ModelPaymentPro::newInstance()->updatePayment($id, array('i_status' => PAYMENT_PRO_COMPLETED));
            osc_add_flash_ok_message(__('The invoice has been paid', 'payment_pro'), 'admin');
        } else {
            foreach($products as $product) {
                osc_run_hook('payment_pro_item_unpaid', $product, $data, $id);
            }
            ModelPaymentPro::newInstance()->updatePayment($id, array('i_status' => PAYMENT_PRO_PENDING));
            osc_add_flash_ok_message(__('The invoice has been unpaid', 'payment_pro'), 'admin');
        }
        osc_redirect_to(osc_route_admin_url('payment-pro-admin-bank'));
        break;
    case 'delete':
        ModelPaymentPro::newInstance()->invoiceDelete($id);
        ob_get_clean();
        osc_add_flash_ok_message(__('The invoice has been deleted', 'payment_pro'), 'admin');
        osc_redirect_to(osc_route_admin_url('payment-pro-admin-bank'));
        break;
    default:
        break;
}


if( Params::getParam('iDisplayLength') != '' ) {
    Cookie::newInstance()->push('listing_iDisplayLength', Params::getParam('iDisplayLength'));
    Cookie::newInstance()->set();
} else {
    // set a default value if it's set in the cookie
    $listing_iDisplayLength = (int) Cookie::newInstance()->get_value('listing_iDisplayLength');
    if ($listing_iDisplayLength == 0) $listing_iDisplayLength = 10;
    Params::setParam('iDisplayLength', $listing_iDisplayLength );
}

$page  = (int)Params::getParam('iPage');
if($page==0) { $page = 1; };
Params::setParam('iPage', $page);

$params = Params::getParamsAsArray();

$bankDataTable = new BankDataTable();
$params['source'] = 'BANK';
$bankDataTable->table($params);
$aData = $bankDataTable->getData();
View::newInstance()->_exportVariableToView('aData', $aData);
View::newInstance()->_exportVariableToView('totalRows', $bankDataTable->totalRows());
View::newInstance()->_exportVariableToView('totalFilteredRows', $bankDataTable->totalFilteredRows());

if(count($aData['aRows']) == 0 && $page!=1) {
    $total = (int)$aData['iTotalDisplayRecords'];
    $maxPage = ceil( $total / (int)$aData['iDisplayLength'] );

    $url = osc_admin_base_url(true).'?'.$_SERVER['QUERY_STRING'];

    if($maxPage==0) {
        $url = preg_replace('/&iPage=(\d)+/', '&iPage=1', $url);
        ob_get_clean();
        osc_redirect_to($url);
    }

    if($page > $maxPage) {
        $url = preg_replace('/&iPage=(\d)+/', '&iPage='.$maxPage, $url);
        ob_get_clean();
        osc_redirect_to($url);
    }
}

$columns    = $aData['aColumns'];
$rows       = $aData['aRows'];

$odd_even = true;
$old_code = '';

?>
<style>
     /* overlay */


    .overlay {
        position:absolute;
        top:0;
        left:0;
        right:0;
        bottom:0;
        background-color:rgba(255, 255, 255, 0.55);
        background: url(data:;base64,iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAABl0RVh0U29mdHdhcmUAUGFpbnQuTkVUIHYzLjUuNUmK/OAAAAATSURBVBhXY2RgYNgHxGAAYuwDAA78AjwwRoQYAAAAAElFTkSuQmCC) repeat scroll transparent\9;
        z-index:9999;
        color:black;
    }
    .overlay {
        text-align: center;
        display: block;
    }

    .overlay:before {
        content: '';
        display: inline-block;
        height: 100%;
        vertical-align: middle;
        margin-right: -0.25em;
    }
    .col-subtotal,
    .col-tax,
    .col-total {
        text-align: right;
    }
    .col-bulkactions {
        display:none;
    }
    .col-bulkactions ~ td {
        padding-bottom: .5rem !important;
    }
</style>
<div class="relative">
    <p>
        <select id="filter-status" class="filter-log" name="status">
            <option <?php echo (Params::getParam('status')==='') ? 'selected' : '';?> value="*"><?php _e('View all status', 'payment_pro'); ?></option>
            <option <?php echo (Params::getParam('status')===(string)PAYMENT_PRO_FAILED) ? 'selected' : '';?> value="<?php echo PAYMENT_PRO_FAILED; ?>"><?php echo $bankDataTable->_status(PAYMENT_PRO_FAILED); ?></option>
            <option <?php echo (Params::getParam('status')===(string)PAYMENT_PRO_COMPLETED) ? 'selected' : '';?> value="<?php echo PAYMENT_PRO_COMPLETED; ?>"><?php echo $bankDataTable->_status(PAYMENT_PRO_COMPLETED); ?></option>
            <option <?php echo (Params::getParam('status')===(string)PAYMENT_PRO_PENDING) ? 'selected' : '';?> value="<?php echo PAYMENT_PRO_PENDING; ?>"><?php echo $bankDataTable->_status(PAYMENT_PRO_PENDING); ?></option>
            <option <?php echo (Params::getParam('status')===(string)PAYMENT_PRO_ALREADY_PAID) ? 'selected' : '';?> value="<?php echo PAYMENT_PRO_ALREADY_PAID; ?>"><?php echo $bankDataTable->_status(PAYMENT_PRO_ALREADY_PAID); ?></option>
            <option <?php echo (Params::getParam('status')===(string)PAYMENT_PRO_WRONG_AMOUNT_TOTAL) ? 'selected' : '';?> value="<?php echo PAYMENT_PRO_WRONG_AMOUNT_TOTAL; ?>"><?php echo $bankDataTable->_status(PAYMENT_PRO_WRONG_AMOUNT_TOTAL); ?></option>
            <option <?php echo (Params::getParam('status')===(string)PAYMENT_PRO_WRONG_AMOUNT_ITEM) ? 'selected' : '';?> value="<?php echo PAYMENT_PRO_WRONG_AMOUNT_ITEM; ?>"><?php echo $bankDataTable->_status(PAYMENT_PRO_WRONG_AMOUNT_ITEM); ?></option>
            <option <?php echo (Params::getParam('status')===(string)PAYMENT_PRO_CREATED) ? 'selected' : '';?> value="<?php echo PAYMENT_PRO_CREATED; ?>"><?php echo $bankDataTable->_status(PAYMENT_PRO_CREATED); ?></option>
        </select>

    </p>
    <form class="table" id="datatablesForm" action="<?php echo osc_admin_base_url(true); ?>" method="post">
        <div class="table-contains-actions">
            <table class="table" cellpadding="0" cellspacing="0">
                <thead>
                <tr>
                    <th class="col-status-border"></th>
                    <?php foreach($columns as $k => $v) {
                        echo '<th class="col-'.$k.' ">'.$v.'</th>';
                    }; ?>
                </tr>
                </thead>
                <tbody>
                <?php if( count($rows) > 0 ) { ?>
                    <?php foreach($rows as $key => $row) {
                        if($row['code']!=$old_code) {
                            $old_code = $row['code'];
                            $odd_even = !$odd_even;
                        } else {
                            $old_code = $row['code'];
                            $row['code'] = '';
                        }
                        $status = $row['status'];
                        $row['status'] = osc_apply_filter('datatable_payment_log_status_text', $row['status']);
                         ?>
                        <tr class="<?php echo osc_apply_filter('datatable_payment_log_status_class',  $status) . " " . ($odd_even?'odd':'even'); ?>">
                            <td class="col-status-border"></td>
                            <?php foreach($row as $k => $v) { ?>
                                <td class="col-<?php echo $k; ?>" data-col-name="<?php echo ucfirst($k); ?>"><?php echo $v; ?></td>
                            <?php }; ?>
                        </tr>
                    <?php }; ?>
                <?php } else { ?>
                    <tr>
                        <td colspan="<?php echo count($columns)+1; ?>" class="text-center">
                            <p><?php _e('No data available in table'); ?></p>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            <div id="table-row-actions"></div> <!-- used for table actions -->
        </div>
    </form>
</div>
<div id="bulkActionsModal" class="modal fade static" tabindex="-1" aria-labelledby="bulkActionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkActionsModalLabel"><?php _e('Bulk actions'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?php _e('Cancel'); ?></button>
                <button id="bulkActionsSubmit" onclick="bulkActionsSubmit()" class="btn btn-sm btn-red"><?php echo osc_esc_html(__('Delete')); ?></button>
            </div>
        </div>
    </div>
</div>

<script>
    $('.filter-log').change( function create_url_log() {
        var new_url = '<?php echo osc_route_admin_url('payment-pro-admin-bank'); ?>' ;
        var status  = $('#filter-status').val();
        var url_changed = false;
        if( status.length > 0 ) {
            new_url = new_url.concat("&status=" + status );
            url_changed = true;
        }
        if(url_changed) {
            $('#content-page').append('<div class="overlay"></div>');
            window.location.href = new_url;
        }
    });
</script>
<?php
function showingResults(){
    $aData = __get('aData');
    $totalRows = __get('totalRows');
    echo '<ul class="showing-results"><li><span>'.osc_pagination_showing((Params::getParam('iPage')-1)*$aData['iDisplayLength']+1, ((Params::getParam('iPage')-1)*$aData['iDisplayLength'])+$totalRows, $aData['iTotalRecords'], $aData['iTotalDisplayRecords']).'</span></li></ul>';
}
osc_add_hook('before_show_pagination_admin','showingResults');
osc_show_pagination_admin($aData);