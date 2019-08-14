<?php

define("__VERSION__", "2016-10-03");
define("__SCRIPTNAME__", basename(__FILE__));

class Mage_CodiScript_IndexController extends Mage_Core_Controller_Front_Action {

  public function indexAction() {
//================== common code start ==================
//class Mage_CodiScript_Model_Files extends Mage_Core_Model_Abstract {
//  public function renderDataFile(){
//  private function ProducttoStringGrouped( &$product ){
//  private function ProducttoStringConfigurable( &$product ){
//  private function ProducttoStringSimple( &$product ){
//  private function _fillConfigProdItems
//  private function _cleanStr( &$str ){
//  private function _correctProdUrlStr( &$str ){
//  private function _getReviews( $productid ){
//  private function _formatPrice( $Price ){
//  private function _formatQty( $qty ){
//  private function _formatImageURL( $str ){
//  private function _getTierPrices( &$product ){
//  public function renderCatalogSection(){
//  private function _drawCategory( &$category, $level=0, $hpath='', $i=1, $j=1, $path='' ){
//  public function renderCatalogProject(){
//  private function _processCategory( &$category, $level=0, $hpath='', $path='' ){
//  private function CatalogProjectStringGroupedOrSimple(&$product) {
//  private function CatalogProjectStringSimple( &$product ){
//  private function CatalogProjectStringConfigurable( &$product ){
//  private function CatalogProjectSortValue(&$product, &$productName, &$categorySorting) {$_DEBUG = $this->getRequest()->getParam("_DEBUG"]) ? $this->getRequest()->getParam("_DEBUG"] : "0";
    $_DEBUG = $this->getRequest()->getParam("_DEBUG") ? $this->getRequest()->getParam("_DEBUG") : "0";
    $_INFO = $this->getRequest()->getParam("_INFO") ? $this->getRequest()->getParam("_INFO") : "0";
    $StoreCode = $this->getRequest()->getParam("Store") ? $this->getRequest()->getParam("Store") : FALSE;
    $Class = $this->getRequest()->getParam("Class") ? $this->getRequest()->getParam("Class") : "";
    $Password = $this->getRequest()->getParam("Password") ? $this->getRequest()->getParam("Password") : "";
// Headers
    $gmdate_mod = gmdate('D, d M Y H:i:s', time()) . ' GMT';
    $this->getResponse()->setHeader('Last-Modified: ' . $gmdate_mod);
    $this->getResponse()->setHeader('Content-Type: text/plain; charset=UTF-8');
    $this->getResponse()->setHeader('Content-Disposition: inline; filename="' . $Class . '.txt"');
    $this->getResponse()->setHeader('X-CoDSoftware-Version: ' . __VERSION__);
// Enable errors display / list versions
    ini_set('display_errors', '1');
    ini_set('error_reporting', E_ALL);
    ini_set('html_errors', '0');
    ini_set('xmlrpc_errors', '0');
    ini_set('error_prepend_string', "");
    ini_set('error_append_string', "");

    function myErrorHandler($errno, $errstr, $errfile, $errline) {
//      $this->getResponse()->appendBody("WARNING: " . $errstr . " (line " . $errline . ")
//");
      return true;
    }

// Set an error handler for warnings and notices.
    set_error_handler('myErrorHandler');
    if ($_DEBUG || $_INFO) {
      $started_time = time();
      $this->getResponse()->appendBody("PHP version: " . phpversion() . "  
Magento version: " . Mage::getVersion() . "    
Script version: " . __VERSION__ . "    
--------------------------------------------------------------------------------
");
    }
    if ($_DEBUG)
      $this->getResponse()->appendBody("Class=" . $Class . "
Password=" . $Password . "
");
    $codiScriptPassword = Mage::helper('codiscript')->getCodiPassword();
// Check if a password is defined
    if ($codiScriptPassword == '') {
      $this->getResponse()->appendBody('ERROR: A blank password is not allowed. Edit the password at System > Configuration > Catalog-on-Demand Configuration.');
      return;
    }
// Check the password
    if ($Password != $codiScriptPassword) {
      $this->getResponse()->appendBody('ERROR: The specified password is invalid.');
      return;
    }
    if ($Class == "Configuration") {
      // nothing special
    } else if ($Class == "DataFile") {
      $enablereviews = ( $this->getRequest()->getParam("enablereviews") && $this->getRequest()->getParam("enablereviews") == "1" );
      $ignoretopcategory = ( $this->getRequest()->getParam("ignoretopcategory") && $this->getRequest()->getParam("ignoretopcategory") == "1" );
      $includeshortdescription = ( $this->getRequest()->getParam("includeshortdescription") && $this->getRequest()->getParam("includeshortdescription") == "1" );
      $includelongdescription = ( $this->getRequest()->getParam("includelongdescription") ? $this->getRequest()->getParam("includelongdescription") == "1" : TRUE );
      $getpricefromchild = ( $this->getRequest()->getParam("getpricefromchild") ? $this->getRequest()->getParam("getpricefromchild") == "1" : TRUE );
      $getgroupprices = ( $this->getRequest()->getParam("getgroupprices") && $this->getRequest()->getParam("getgroupprices") == "1" );
      $publishtieredpricing = ( $this->getRequest()->getParam("publishtieredpricing") && $this->getRequest()->getParam("publishtieredpricing") == "1" );
      $quantitylabel = $this->getRequest()->getParam("quantitylabel") ? $this->getRequest()->getParam("quantitylabel") : "Quantity";
      $pricelabel = $this->getRequest()->getParam("pricelabel") ? $this->getRequest()->getParam("pricelabel") : "Price";
      $savingslabel = $this->getRequest()->getParam("savingslabel") ? $this->getRequest()->getParam("savingslabel") : "Savings";
      $includetaxes = ( $this->getRequest()->getParam("includetaxes") && $this->getRequest()->getParam("includetaxes") == "1" );
      $includeinvqty = ( $this->getRequest()->getParam("includeinvqty") && $this->getRequest()->getParam("includeinvqty") == "1" );
      $includespecialprice = ( $this->getRequest()->getParam("includespecialprice") && $this->getRequest()->getParam("includespecialprice") == "1" );
      $includespecialpricedatefrom = ( $this->getRequest()->getParam("includespecialpricedatefrom") && $this->getRequest()->getParam("includespecialpricedatefrom") == "1" );
      $includespecialpricedateto = ( $this->getRequest()->getParam("includespecialpricedateto") && $this->getRequest()->getParam("includespecialpricedateto") == "1" );
      $ignoreexcludedimages = ( $this->getRequest()->getParam("ignoreexcludedimages") && $this->getRequest()->getParam("ignoreexcludedimages") == "1" );
      $ignoreassprodimages = ( $this->getRequest()->getParam("ignoreassprodimages") && $this->getRequest()->getParam("ignoreassprodimages") == "1" );
      $includecustomfields = $this->getRequest()->getParam("includecustomfields") ? trim($this->getRequest()->getParam("includecustomfields")) : FALSE;
      if (empty($includecustomfields)) {
        $includecustomfields = FALSE;
      } else {
        $arr = explode(",", $includecustomfields);
        $includecustomfields = array();
        foreach ($arr as $part) {
          $part = trim($part);
          if (!empty($part)) {
            $includecustomfields[strtolower($part)] = TRUE;
          }
        }
      }
      $importoptionsasattributes = ( $this->getRequest()->getParam("importoptionsasattributes") && $this->getRequest()->getParam("importoptionsasattributes") == "1" );
      $importoptionsassku = ( $this->getRequest()->getParam("importoptionsassku") && $this->getRequest()->getParam("importoptionsassku") == "1" );
      $instockonly = ( $this->getRequest()->getParam("instockonly") && $this->getRequest()->getParam("instockonly") == "1" );
      $splitgroupedproducts = ( $this->getRequest()->getParam("splitgroupedproducts") && $this->getRequest()->getParam("splitgroupedproducts") == "1");
      $start = $this->getRequest()->getParam("start") ? intval($this->getRequest()->getParam("start")) : 0;
      $pageSize = $this->getRequest()->getParam("pageSize") ? intval($this->getRequest()->getParam("pageSize")) : 1000000000;
      if ($_DEBUG) {
        $this->getResponse()->appendBody("enablereviews=" . $enablereviews . "
ignoretopcategory=" . $ignoretopcategory . "
includeshortdescription=" . $includeshortdescription . "
includelongdescription=" . $includelongdescription . "
getpricefromchild=" . $getpricefromchild . "
getgroupprices=" . $getgroupprices . "
publishtieredpricing=" . $publishtieredpricing . "
quantitylabel=" . $quantitylabel . "
pricelabel=" . $pricelabel . "
savingslabel=" . $savingslabel . "
includetaxes=" . $includetaxes . "
includeinvqty=" . $includeinvqty . "
includespecialprice=" . $includespecialprice . "
includespecialpricedatefrom=" . $includespecialpricedatefrom . "
includespecialpricedateto=" . $includespecialpricedateto . "
ignoreexcludedimages=" . $ignoreexcludedimages . "
ignoreassprodimages=" . $ignoreassprodimages . "
includecustomfields=" . ( $includecustomfields ? implode(",", array_keys($includecustomfields)) : $includecustomfields ) . "
importoptionsasattributes=" . $importoptionsasattributes . "
importoptionsassku=" . $importoptionsassku . "
instockonly=" . $instockonly . "
splitgroupedproducts=" . $splitgroupedproducts . "
start=" . $start . "
pageSize=" . $pageSize . "
");
      }
    } else if ($Class == "CatalogSection") {
      $ignoretopcategory = ( $this->getRequest()->getParam("ignoretopcategory") && $this->getRequest()->getParam("ignoretopcategory") == "1" );
      if ($_DEBUG) {
        $this->getResponse()->appendBody("ignoretopcategory=" . $ignoretopcategory . "
");
      }
    } else if ($Class == "CatalogProject") {
      $ignoretopcategory = ( $this->getRequest()->getParam("ignoretopcategory") && $this->getRequest()->getParam("ignoretopcategory") == "1" );
      $instockonly = ( $this->getRequest()->getParam("instockonly") && $this->getRequest()->getParam("instockonly") == "1" );
      $splitgroupedproducts = ( $this->getRequest()->getParam("splitgroupedproducts") && $this->getRequest()->getParam("splitgroupedproducts") == "1");
      $start = $this->getRequest()->getParam("start") ? intval($this->getRequest()->getParam("start")) : 0;
      $pageSize = $this->getRequest()->getParam("pageSize") ? intval($this->getRequest()->getParam("pageSize")) : 1000000000;
      if ($_DEBUG) {
        $this->getResponse()->appendBody("ignoretopcategory=" . $ignoretopcategory . "
instockonly=" . $instockonly . "
splitgroupedproducts=" . $splitgroupedproducts . "
start=" . $start . "
pageSize=" . $pageSize . "
");
      }
    }
// Increase memory limit to 1024M
    ini_set('memory_limit', '1024M');
// Increase maximum execution time to 6 hours
    ini_set('max_execution_time', 28800);
// Make sure GC is enabled
    if (function_exists("gc_enable")) {
      gc_enable();
    } else if ($_DEBUG || $_INFO) {
      $this->getResponse()->appendBody("gc_enable does not exist.
");
    }
    if ($_INFO) {
      foreach (Mage::app()->getStores() as $store) {
        $this->getResponse()->appendBody("store: " . $store->getId() . " code=" . $store->getCode() . " name=" . $store->getName() . " isActive=" . $store->getIsActive() . "
");
      }
      if (function_exists("gc_enabled")) {
        $this->getResponse()->appendBody("gc_enabled=" . gc_enabled() . "
");
      } else {
        $this->getResponse()->appendBody("gc_enabled does not exist.
");
      }
      $this->getResponse()->appendBody("=========================================================================================================
");
      return;
    }
// Determine / check store
    $StoreId = 0;
    $Store = FALSE;
    $Stores = Mage::app()->getStores(false, true);
    if (!empty($StoreCode) && !$Stores[$StoreCode]) {
      $this->getResponse()->appendBody("ERROR: Store \"" . $StoreCode . "\" not found.");
      return;
    } else if (!empty($StoreCode) && !$Stores[$StoreCode]->getIsActive()) {
      $this->getResponse()->appendBody("ERROR: Store \"" . $StoreCode . "\" is inactive (disabled).");
      return;
    } else if (!empty($StoreCode)) {
      $Store = $Stores[$StoreCode];
      $StoreId = $Store->getId();
    } else {
      $count = 0;
      foreach ($Stores as $_Store) {
        if ($_Store->getIsActive()) {
          $count++;
          $Store = $_Store;
          $StoreId = $Store->getId();
        }
      }
      if ($count > 1) {
        $this->getResponse()->appendBody("ERROR: Store specification is required (there are more than 1 active / enabled stores).");
        return;
      }
      if ($count < 1) {
        $this->getResponse()->appendBody("ERROR: No active stores.");
        return;
      }
    }

//================== common code end ==================
    $cfModel = Mage::getModel('codiscript/files');
    $cfModel->controller = $this;
//================== common code start ==================
    if ($Class == "Configuration") {
      // nothing special
    } else if ($Class == "DataFile") {
      $cfModel->enablereviews = $enablereviews;
      $cfModel->ignoretopcategory = $ignoretopcategory;
      $cfModel->includeshortdescription = $includeshortdescription;
      $cfModel->includelongdescription = $includelongdescription;
      $cfModel->getpricefromchild = $getpricefromchild;
      $cfModel->getgroupprices = $getgroupprices;
      $cfModel->publishtieredpricing = $publishtieredpricing;
      $cfModel->quantitylabel = $quantitylabel;
      $cfModel->pricelabel = $pricelabel;
      $cfModel->savingslabel = $savingslabel;
      $cfModel->includetaxes = $includetaxes;
      $cfModel->includeinvqty = $includeinvqty;
      $cfModel->includespecialprice = $includespecialprice;
      $cfModel->includespecialpricedatefrom = $includespecialpricedatefrom;
      $cfModel->includespecialpricedateto = $includespecialpricedateto;
      $cfModel->ignoreexcludedimages = $ignoreexcludedimages;
      $cfModel->ignoreassprodimages = $ignoreassprodimages;
      $cfModel->includecustomfields = $includecustomfields;
      $cfModel->importoptionsasattributes = $importoptionsasattributes;
      $cfModel->importoptionsassku = $importoptionsassku;
      $cfModel->instockonly = $instockonly;
      $cfModel->splitgroupedproducts = $splitgroupedproducts;
      $cfModel->start = $start;
      $cfModel->pageSize = $pageSize;
      if ($cfModel->includetaxes) {
        $cfModel->taxhelper = Mage::helper('tax');
      }
    } else if ($Class == "CatalogSection") {
      $cfModel->ignoretopcategory = $ignoretopcategory;
    } else if ($Class == "CatalogProject") {
      $cfModel->ignoretopcategory = $ignoretopcategory;
      $cfModel->instockonly = $instockonly;
      $cfModel->splitgroupedproducts = $splitgroupedproducts;
      $cfModel->start = $start;
      $cfModel->pageSize = $pageSize;
    }
    $cfModel->_DEBUG = $_DEBUG;
    $cfModel->StoreId = $StoreId;
    $cfModel->Store = $Store;
    if ($Class == "Configuration") {
      $cfModel->renderConfiguration();
    } else if ($Class == "DataFile") {
      $cfModel->renderDataFile();
    } else if ($Class == "CatalogSection") {
      $cfModel->renderCatalogSection();
    } else if ($Class == "CatalogProject") {
      $cfModel->renderCatalogProject();
    } else if ($_DEBUG) {
      foreach (Mage::app()->getStores() as $store) {
        $this->getResponse()->appendBody("store: " . $store->getId() . " code=" . $store->getCode() . " name=" . $store->getName() . " isActive=" . $store->getIsActive() . "
website=" . $store->getWebsite() . "
base currency=" . $store->getBaseCurrency() . "
");
      }
      $this->getResponse()->appendBody("max_execution_time=" . ini_get("max_execution_time") . "
memory_limit=" . ini_get("memory_limit") . "
");
      if (function_exists("gc_enabled")) {
        $this->getResponse()->appendBody("gc_enabled=" . gc_enabled() . "
");
      } else {
        $this->getResponse()->appendBody("gc_enabled does not exist.
");
      }
      $this->getResponse()->appendBody("--------------------------------------------------------------------------------
");
      phpinfo(INFO_GENERAL);
      phpinfo(INFO_CONFIGURATION);
      $this->getResponse()->appendBody("--------------------------------------------------------------------------------
");
    } else {
      $this->getResponse()->appendBody("==EOF==");
    }
    if ($_DEBUG) {
      $this->getResponse()->appendBody("
executed in " . ( time() - $started_time ) . " sec. 
");
    }
    return;
    /* RELEASE NOTES
     * 2016-05-30
     * Added product sort options
     *
     * 2015-07-09
     * Improved rendering of image URLs
     *
     * 2015-05-11
     * Improved rendering of prices with store-dependent taxes
     *
     * 2015-05-08
     * Added rendering of products associated with grouped prodict as separate products
     *
     * 2015-04-27
     * Added rendering of group tier prices in case of "Get group prices" and "Publish tiered pricing" both checked
     *
     * 2015-04-23
     * Minor fixes
     *
     * 2015-04-14
     * Added import of custom simple product options into items, if the option contains a SKU number
     *
     * 2015-03-30
     * Added retrieval of custom product options in case of importoptionsasattributes=true
     *
     * 2015-03-02
     * Filtering out disabled products improved for associated products of configurable products
     *
     * 2015-02-17
     * Fixed call-time pass-by-reference bug 
     *
     * 2015-01-29
     * Restored / reimplemented getpricefromchild 
     * - now forces getting individual associated product prices as item prices
     *
     * 2015-01-08
     * 1. Optimized configurable product items ordering
     *
     * 2014-12-16
     * 1. Fixed configurable attributes processing bug
     *
     * 2014-11-10
     * 1. Added instockonly option and implemented import of only items that are in stock
     *
     * 2014-10-17
     * 1. Improved configurable product item attribute export
     *
     * 2014-10-12
     * 1. Improved price formatting (added proper "rounding half up")
     *
     * 2014-08-22
     * 1. publishtieredpricing causes retrieval of tier prices for individual products within a group product
     *
     * 2014-08-06
     * 1. Added importoptionsasattributes option / processing
     *
     * 2014-04-01
     * 1. Group prices retrieval implemented (Magento 1.7+)
     * 2. Obsoleted getpricefromchild option
     *
     * 2014-02-24
     * 1. includecustomfields parameter added / implemented
     * 2. Improved langiage-specific section rendering
     *
     * 2014-02-04
     * 1. "MagentoBase" role introduced for base product images.
     * 2. Improved product attributes import: excluded attributes of "select" / "muitiselect" type having no selection
     *
     * * 2014-01-18
     * 1. Configurable products - pricing updates / fixes
     * 2. Configurable products - items ordering updates
     * 3. Bundled products -pricing updates
     *
     * 2014-01-16
     * 1. Fixed sequencing issue for grouped products
     *
     * 2013-10-30
     * 1. Added includespecialprice / includespecialpricedatefrom / includespecialpricedateto request parameter / processing
     *    (population of item attributes with special price / dates)
     * 2. Added incremental CatalogSection / CatalogProject file retrieval
     *
     * 2013-10-15
     * 1. Added includeinvqty request parameter / processing
     *
     * 2013-07-19
     * 1. Added incremental data file retrieval
     *
     * 2013-06-05
     * 1. "Ignore associated product images" feature expanded to configurable products
     *
     * 2013-05-31
     * 1. Added "Ignore associated product images" feature
     *
     * 2013-02-04
     * 1. Added includelongdescription request parameter / processing
     *
     * 2012-12-07
     * 1. Improved error / warning / notice reporting
     * 2. Fixed configurable attributes import error
     *
     * 2012-12-04
     * 1. Updated tier prices import
     *
     * 2012-11-05
     * 1. Updated multiple images import
     * 2. Added "Ignore excluded images" option support
     *
     * 2012-11-01
     * 1. Updated debugging outout
     *
     * 2012-10-30
     * 1. Added "Include taxes" option support
     * 2. Updated images import: all product images are imported, not only the base one
     *
     * 2012-10-22
     * 1. Added "Ignore top category" option support
     *
     * 2012-07-26
     * 1. Added store spefification
     * 2. Minor performance improvements
     *
     * 2012-07-25
     * Minor performance improvements
     *///==============================================================================
  }

}
