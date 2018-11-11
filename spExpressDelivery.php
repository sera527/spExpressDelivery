<?php
/**
* 2007-2018 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2018 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_')) {
    exit;
}

class spExpressDelivery extends CarrierModule
{
    public $id_carrier;
    public $output;

    protected $config_form = false;

    public function __construct()
    {
        $this->name = 'spExpressDelivery';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Serhii Posternak';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Express Delivery');
        $this->description = $this->l('You can create delivery method for some postcode and show it at certain times of the day.');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('updateCarrier');
    }

    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (Tools::isSubmit('add' . $this->name) || Tools::isSubmit('edit' . $this->name)) {
            return $this->displayCarrierForm();
        } elseif (Tools::isSubmit('save_' . $this->name)) {
            $this->output .= $this->saveCarrier();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        return $this->output.$this->displayCarrierList();
    }

    private function displayCarrierList()
    {
        $this->fields_list = array(
            'name' => array(
                'type' => 'text',
                'title' => $this->l('Name'),
                'name' => 'name',
            ),
            'postcodes' => array(
                'type' => 'text',
                'title' => $this->l('Postal codes'),
                'name' => 'postal_codes',
            ),
            'until_time' => array(
                'type' => 'text',
                'title' => $this->l('Show until time'),
                'name' => 'until_time',
            ),
            'price' => array(
                'type' => 'text',
                'title' => $this->l('Price'),
                'name' => 'price',
            ),
            'category' => array(
                'type' => 'text',
                'title' => $this->l('Products category'),
                'name' => 'category',
            ),
        );

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = false;
        $helper->actions = array('edit', 'delete');
        $helper->show_toolbar = true;
        $helper->identifier = 'id_spExpressDelivery';
        $helper->toolbar_btn['new'] =  array(
            'href' => AdminController::$currentIndex . '&configure=' . $this->name . '&add' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'desc' => $this->l('Add new', array(), 'Admin.Actions')
        );
        $helper->title = $this->l('Carriers');
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . "&edit" . $this->name;
        $content = $this->getCarriersForList();
        $helper->listTotal = count($content);
        return $helper->generateList($content, $this->fields_list);
    }

    protected function getCarriersForList()
    {
        $carriers = Db::getInstance()->executeS("SELECT * FROM " . _DB_PREFIX_ . "spExpressDelivery");
        foreach ($carriers as &$carrier) {
            $carrierInfo = new Carrier($carrier['id_spExpressDelivery'], $this->context->language->id);
            $category = new Category($carrier['category'], $this->context->language->id);
            $carrier['name'] = $carrierInfo->name;
            $carrier['category'] = $category->name;
        }
        return $carriers;
    }

    protected function getCarrier($id)
    {
        $carrier = Db::getInstance()->getRow("SELECT * FROM " . _DB_PREFIX_ . "spExpressDelivery WHERE `id_spExpressDelivery` = {$id}");
        $carrierInfo = new Carrier($id, $this->context->language->id);
        $carrier['name'] = $carrierInfo->name;
        return $carrier;
    }
    protected function saveCarrier()
    {
        $name = Tools::getValue('name');
        $price = Tools::getValue('price');
        $category = Tools::getValue('category');
        if ($name && $price && $category) {
            $id = Tools::getValue('id');
            if ($id) {
                $carrier = new Carrier($id);
                $carrier->name = Tools::getValue('name');
                $this->savePostCodes($carrier->id, Tools::getValue('postcodes'), Tools::getValue('country'));
                $sql = "UPDATE `" . _DB_PREFIX_ . "spExpressDelivery`
                SET `postcodes` = " . (Tools::getValue('postcodes') ? '\''.Tools::getValue('postcodes').'\'' : 'NULL') .
                    ", `until_time` = " . (Tools::getValue('until_time') ? '\''.Tools::getValue('until_time').'\'' : 'NULL') .
                    ", `country` = " . (Tools::getValue('country') ? '\''.Tools::getValue('country').'\'' : 'NULL') .
                    ", `price` = {$price}, `category` = {$category} " .
                    "WHERE `id_spExpressDelivery` = {$id}";
                if ($carrier->update() && Db::getInstance()->execute($sql)) {
                    return $this->displayConfirmation($this->l('Carrier updated'));
                }
                return $this->displayError($this->l("Error occured during data saving."));
            } else {
                $carrier = new Carrier();
                $carrier->name = $name;
                $carrier->is_module = true;
                $carrier->active = 1;
                $carrier->range_behavior = 1;
                $carrier->need_range = 1;
                $carrier->shipping_external = true;
                $carrier->range_behavior = 0;
                $carrier->external_module_name = $this->name;
                $carrier->shipping_method = 2;

                foreach (Language::getLanguages() as $lang)
                    $carrier->delay[$lang['id_lang']] = $this->l('Express delivery');

                if ($carrier->add() == true) {
                    @copy(dirname(__FILE__).'/views/img/carrier_image.jpg', _PS_SHIP_IMG_DIR_.'/'.(int)$carrier->id.'.jpg');
                    $this->addZones($carrier);
                    $this->addGroups($carrier);
                    $this->addRanges($carrier);
                    $this->savePostCodes($carrier->id, Tools::getValue('postcodes'), Tools::getValue('country'));
                    $sql = "INSERT INTO `" . _DB_PREFIX_ . "spExpressDelivery` (`id_spExpressDelivery`, `postcodes`, `until_time`, `price`, `category`, `country`)
                        VALUES ({$carrier->id}, " . (Tools::getValue('postcodes') ? '\''.Tools::getValue('postcodes').'\'' : 'NULL') .
                            ", " . (Tools::getValue('until_time') ? '\''.Tools::getValue('until_time').'\'' : 'NULL') .
                            ", {$price}, {$category}, ".
                            (Tools::getValue('country') ? '\''.Tools::getValue('country').'\'' : 'NULL').")";
                    if (Db::getInstance()->execute($sql)) {
                        return $this->displayConfirmation($this->l('Carrier created'));
                    }
                }
                return $this->displayError($this->l("Error occured during data saving."));
            }
        }
        return $this->displayError($this->l("There are no required parameters."));
    }

    private function savePostCodes($carrierId, $codes, $countryId)
    {
        Db::getInstance()->delete("spExpressDelivery_postcodes", "carrier = {$carrierId}");
        $codes = preg_replace('/\s+/', '', $codes);
        $codes = explode(',', $codes);
        $country = new Country($countryId);
        foreach ($codes as $code) {
            if (strstr($code, '-') === false) {
                if (!$country->checkZipCode($code)) {
                    $this->output .= $this->displayError($this->l('Invalid postcode - should look like ') . $country->zip_code_format);
                    return false;
                }
                $sql = "INSERT INTO `" . _DB_PREFIX_ . "spExpressDelivery_postcodes`
                        VALUES ({$code}, {$carrierId})";
                Db::getInstance()->execute($sql);
            } else {
                $interval = explode('-', $code);
                if (!$country->checkZipCode($interval[0])) {
                    $this->output .= $this->displayError($this->l('Invalid postcode - should look like ') . $country->zip_code_format);
                    return false;
                }
                if (!$country->checkZipCode($interval[1])) {
                    $this->output .= $this->displayError($this->l('Invalid postcode - should look like ') . $country->zip_code_format);
                    return false;
                }
                for ($i = (int)$interval[0]; $i <= (int)$interval[1]; $i++) {
                    $l2 = strlen($country->zip_code_format);
                    $l1 = strlen($i);
                    for ($t = 0; $t < ($l2 - $l1); $t++) {
                        $i = "0" . $i;
                    }
                    $sql = "INSERT INTO `" . _DB_PREFIX_ . "spExpressDelivery_postcodes`
                        VALUES ('{$i}', {$carrierId})";
                    Db::getInstance()->execute($sql);
                }
            }
        }
        return true;
    }

    private function displayCarrierForm()
    {
        $carrier = null;
        $category = null;
        if (Tools::getValue('id_spExpressDelivery')) {
            $carrier = $this->getCarrier(Tools::getValue('id_spExpressDelivery'));
            $category = $carrier['category'];
        }
        $countries = Country::getCountries($this->context->language->id, true);
        $this->fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->l('Create/update carrier'),
            ),
            'input' => [
                array(
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'required' => true,
                ),
                array(
                    'type' => 'textarea',
                    'label' => $this->l('Postal codes'),
                    'name' => 'postcodes',
                ),
                array(
                    'type' => 'select',
                    'label' => $this->l('Country'),
                    'name' => 'country',
                    'options' => array(
                        'query' => $countries,
                        'id' => 'id_country',
                        'name' => 'name'
                    )
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Show until time'),
                    'name' => 'until_time',
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('Price'),
                    'name' => 'price',
                    'required' => true,
                ),
                array(
                    'type' => 'categories',
                    'label' => $this->l('Products category'),
                    'name' => 'category',
                    'required' => true,
                    'tree' => array(
                        'id' => 'id_category',
                        'selected_categories' => array($category)
                    )
                ),
            ],
            'submit' => array(
                'title' => $this->l('Save', array(), 'Admin.Actions'),
            )
        );
        $helper = new HelperForm();
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => (array) $carrier,
        ];
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name . "&id=" . Tools::getValue('id_spExpressDelivery');
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->title = $this->displayName;
        $helper->submit_action = 'save_' . $this->name;
        return $helper->generateForm($this->fields_form);
    }

    private function isCodeBelongsToCarrier($carrierId, $code)
    {
        Db::getInstance()->executeS("SELECT * FROM `" . _DB_PREFIX_ . "spExpressDelivery_postcodes` WHERE `carrier` = {$carrierId}");
        if (Db::getInstance()->numRows() === 0) {
            return true;
        }
        return (bool) Db::getInstance()->getRow("SELECT * FROM `" . _DB_PREFIX_ . "spExpressDelivery_postcodes` WHERE `carrier` = {$carrierId} AND `code` = '{$code}'");
    }

    private function checkOneProductBelongsToCategory($category, $products)
    {
        foreach ($products as $product) {
            if (Product::idIsOnCategoryId($product['id_product'], array($category))) {
                return true;
            }
        }
        return false;
    }

    public function getOrderShippingCost($params, $shipping_cost)
    {
        if (Context::getContext()->customer->logged == true)
        {
            $carrier = $this->getCarrier($this->id_carrier);
            $products = $params->getProducts();
            if (!$this->checkOneProductBelongsToCategory($carrier['category'], $products)) {
                return false;
            }
            $address = new Address($params->id_address_invoice);
            if (!$this->isCodeBelongsToCarrier($this->id_carrier, $address->postcode)) {
                return false;
            }
            if ($carrier['until_time'] != '') {
                list($h, $i, $s) = explode(':', $carrier['until_time']);
                $time = new DateTime();
                $time->setTime($h, $i, $s);
                $now = new DateTime();
                if ($now >= $time) {
                    return false;
                }
            }
            return $carrier['price'];
        }
        return $shipping_cost;
    }

    public function getOrderShippingCostExternal($params)
    {
        return true;
    }

    protected function addGroups($carrier)
    {
        $groups_ids = array();
        $groups = Group::getGroups(Context::getContext()->language->id);
        foreach ($groups as $group)
            $groups_ids[] = $group['id_group'];

        $carrier->setGroups($groups_ids);
    }

    protected function addRanges($carrier)
    {
        $range_price = new RangePrice();
        $range_price->id_carrier = $carrier->id;
        $range_price->delimiter1 = '0';
        $range_price->delimiter2 = '10000';
        $range_price->add();

        $range_weight = new RangeWeight();
        $range_weight->id_carrier = $carrier->id;
        $range_weight->delimiter1 = '0';
        $range_weight->delimiter2 = '10000';
        $range_weight->add();
    }

    protected function addZones($carrier)
    {
        $zones = Zone::getZones();

        foreach ($zones as $zone)
            $carrier->addZone($zone['id_zone']);
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookUpdateCarrier($params)
    {
        /**
         * Not needed since 1.5
         * You can identify the carrier by the id_reference
        */
    }
}
