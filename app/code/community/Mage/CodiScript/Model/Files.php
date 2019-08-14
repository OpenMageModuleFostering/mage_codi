<?php

// 2016-05-30
class ConfiguredProductLine {

  public $optionIds = array();
  public $line = NULL;

}

class COption {

  public $id = NULL;
  public $title = NULL;
  public $type = NULL;
  public $required = NULL;
  public $values = array();

  public function toString() {
    return "COption #" . $this->id . " title=" . $this->title . " type=" . $this->type . " required=" . $this->required . " values=" . count($this->values);
  }

}

class COValue {

  public $title = NULL;
  public $sku = NULL;
  public $rprice = NULL;
  public $sprice = NULL;

  public function toString() {
    return "COValue " . $this->title . " sku=" . $this->sku . " rprice=" . $this->rprice . " sprice=" . $this->sprice;
  }

}

class COValueSet {

  public $optionId = NULL;
  public $coValues = array();

  public function toString() {
    $out = "COValueSet #" . $this->optionId . " ";
    foreach ($this->coValues as $value) {
      $out .= $value->sku ? "-" . $value->sku : "-...";
    }
    return $out;
  }

}

class Mage_CodiScript_Model_Files extends Mage_Core_Model_Abstract {

  public $version = __VERSION__;
  public $controller;
  public $Store;
  public $StoreId;
  public $reviewsModel;
  public $catalogInventoryModel;
  public $productTypeGroupedModel;
  public $productTypeConfigurableModel;
  public $customerGroups = FALSE;
  public $mageVersionArray = FALSE;
  public $enablereviews = FALSE;
  public $ignoretopcategory = FALSE;
  public $includeshortdescription = FALSE;
  public $includelongdescription = TRUE;
  public $getpricefromchild = FALSE;
  public $getgroupprices = FALSE;
  public $publishtieredpricing = FALSE;
  public $includetaxes = FALSE;
  public $includeinvqty = FALSE;
  public $includespecialprice = FALSE;
  public $includespecialpricedatefrom = FALSE;
  public $includespecialpricedateto = FALSE;
  public $ignoreexcludedimages = FALSE;
  public $ignoreassprodimages = FALSE;
  public $includecustomfields = FALSE;
  public $importoptionsasattributes = FALSE;
  public $importoptionsassku = FALSE;
  public $instockonly = FALSE;
  public $splitgroupedproducts = FALSE;
  public $start = 0;
  public $pageSize = 1000000000;
  public $address = FALSE;
  public $quantitylabel;
  public $pricelabel;
  public $savingslabel;
  public $mediaurl;
  public $prodmediaurl;
  public $taxhelper;
  public $unCatPosition;
  public $catpathmap;
  public $catmap;
  public $catpositions = FALSE;
  public $storeSorting = "position";
  public $_DEBUG;

  const TAG_P_CLOSE = '</p>';
  const TAG_P = '<p>';
  const SCRIPTNAME = __SCRIPTNAME__;

  public function _construct() {
    parent::_construct();
    $this->_init('codi/codi2');
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
  }

  // Class=Settings
  public function renderConfiguration() {
    $this->controller->getResponse()->appendBody("Configuration: start
ProductListDefaultSortBy: " . Mage::getSingleton('catalog/config')->getProductListDefaultSortBy($this->StoreId) . "
Configuration: end
");
  }

  // Class=DataFile
  public function renderDataFile() {
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("memory_get_usage real: " . memory_get_usage(TRUE) . " allocated: " . memory_get_usage() . "
");
      foreach (Mage::app()->getStores() as $store) {
        $this->controller->getResponse()->appendBody("store: " . $store->getId() . " code=" . $store->getCode() . " isActive=" . $store->getIsActive() . "
");
      }
    }
    $this->productTypeConfigurableModel = Mage::getModel('catalog/product_type_configurable');
    $this->productTypeGroupedModel = Mage::getModel('catalog/product_type_grouped');
    $this->reviewsModel = Mage::getModel('review/review')->setStoreId($this->StoreId);
    $this->catalogInventoryModel = Mage::getModel('cataloginventory/stock_item')->setStoreId($this->StoreId);
    $this->mediaurl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
    $this->prodmediaurl = $this->mediaurl . "catalog/product";
    $this->mageVersionArray = Mage::getVersionInfo();
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("mageVersionArray: major " . $this->mageVersionArray['major'] . " minor " . $this->mageVersionArray['minor'] . "
");
    }
    if ($this->publishtieredpricing || $this->getgroupprices) {
      $this->customerGroups = array();
      $customerGroups = Mage::getModel('customer/group')->getCollection();
      foreach ($customerGroups as $cg) {
        $this->customerGroups[$cg->_data['customer_group_id']] = $cg->_data['customer_group_code'];
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("customer group #" . $cg->_data['customer_group_id'] . "-" . $cg->_data['customer_group_code'] . "
");
        }
      }
    }
    $products = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->getCollection();
    $products->addAttributeToFilter('status', 1); //enabled
    $products->addAttributeToFilter('visibility', 4); //catalog, search
    $prodIds = $products->getAllIds();
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("StoreId: " . $this->StoreId . "
Products: " . count($prodIds) . "
Media URL: " . $this->mediaurl . "
memory_get_usage " . memory_get_usage() . " / " . memory_get_usage(TRUE) . "
");
    }
    if ($this->start == 0) {
      $this->controller->getResponse()->appendBody("itemNumber\titemQty\titemUom\titemPrice\titemDescription\titemLink\titemAttributes\titemGraphic\tproductName\tproductDescription\tproductGraphic\tproductLink\tproductAttributes\tManufacturer\tCategory\tReviews\tSupplementalInfo\titemSequence
");
    }
    $count = 0;
    $index = 0;
    $lastLine = "==EOF==
";
    foreach ($prodIds as $productId) {
      if ($count == $this->pageSize) {
        $lastLine = "==MORE==
";
        break;
      }
      if ($index < $this->start) {
        $index++;
        continue;
      }
      $index++;
      $product = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->load($productId);
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("== PRODUCT: " . $product->getTypeId() . " == " . $product->getId() . "-" . $product->getName() . "  ==
");
      }
      if ($product->isConfigurable()) {
        $this->controller->getResponse()->appendBody($this->ProducttoStringConfigurable($product));
      } else if ($product->isGrouped()) {
        if ($this->splitgroupedproducts) {
          $AssociatedProductIds = $this->productTypeGroupedModel->getAssociatedProductIds($product);
          foreach ($AssociatedProductIds as $UsedProductid) {
            $_product = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->load($UsedProductid);
            $this->controller->getResponse()->appendBody($this->ProducttoStringSimple($_product));
          }
        } else {
          $this->controller->getResponse()->appendBody($this->ProducttoStringGrouped($product));
        }
      } else {
        $this->controller->getResponse()->appendBody($this->ProducttoStringSimple($product));
      }
      unset($product);
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("#" . $count . ": memory_get_usage " . memory_get_usage() . " / " . memory_get_usage(TRUE) . "
");
      }
      $count++;
    }
    $this->controller->getResponse()->appendBody($lastLine);
  }

  private function ProducttoStringGrouped(&$product) {
    $ProducttoString = "";
    $AssociatedProductIds = $this->productTypeGroupedModel->getAssociatedProductIds($product);
    $ProductDescription = "";
    $shortDescription = "";
    if ($this->includeshortdescription) {
      $shortDescription = $product->getShortDescription();
      if (!empty($shortDescription)) {
        $shortDescription = $this->_cleanStr($shortDescription);
        if (strpos($shortDescription, self::TAG_P) !== false) {
          $new = strlen($shortDescription);
          $pos = strrpos($shortDescription, self::TAG_P_CLOSE) + 4;
          if ($new != $pos) {
            $shortDescription = substr($shortDescription, 0, $pos) . self::TAG_P . substr($shortDescription, $pos) . self::TAG_P_CLOSE;
          }
        } else {
          $shortDescription = self::TAG_P . $shortDescription . self::TAG_P_CLOSE;
        }
        $ProductDescription = $shortDescription;
      }
    }
    if ($this->includelongdescription) {
      $longDescription = $product->getDescription();
      if (!empty($longDescription)) {
        $longDescription = $this->_cleanStr($longDescription);
        if (strpos($longDescription, self::TAG_P) !== false) {
          $new = strlen($longDescription);
          $pos = strrpos($longDescription, self::TAG_P_CLOSE) + 4;
          if ($new != $pos) {
            $longDescription = substr($longDescription, 0, $pos) . self::TAG_P . substr($longDescription, $pos) . self::TAG_P_CLOSE;
          }
        } else {
          $longDescription = self::TAG_P . $longDescription . self::TAG_P_CLOSE;
        }
        $ProductDescription .= $longDescription;
      }
    }
    $attributes = $product->getAttributes();
    $Manufacturer = "";
    $ProductAttributes = "";
    foreach ($attributes as $attribute) {
      $attribute->setStoreId($this->StoreId);
      if ($this->_DEBUG && ( $attribute->getFrontendInput() == "select" || $attribute->getFrontendInput() == "multiselect" )) {
        $hasData = $product->getData($attribute->getAttributeCode()) ? TRUE : FALSE;
        $this->controller->getResponse()->appendBody("grouped prod attr: " . $attribute->getFrontend()->getLabel() . "=" . $attribute->getFrontend()->getValue($product) . " input=" . $attribute->getFrontendInput() . " data=" . $product->getData($attribute->getAttributeCode()) . " hasData=" . $hasData . "
");
      }
      if ($attribute->getAttributeCode() == "manufacturer") {
        if ($product->getData($attribute->getAttributeCode())) {
          $Manufacturer = $attribute->getFrontend()->getValue($product);
        } else {
          $Manufacturer = "";
        }
        continue;
      }
      if ($attribute->getIsVisibleOnFront() ||
        ( $this->includecustomfields && array_key_exists(strtolower($attribute->getAttributeCode()), $this->includecustomfields) )) {
        $value = $attribute->getFrontend()->getValue($product);
        if ($attribute->getFrontendInput() == "select" || $attribute->getFrontendInput() == "multiselect") {
          $hasData = $product->getData($attribute->getAttributeCode()) ? TRUE : FALSE;
          if (!$hasData) {
            $value = "";
          }
        }
        if (is_string($value) && strlen($value)) {
          if (!empty($ProductAttributes)) {
            $ProductAttributes .= "|";
          }
          $ProductAttributes .= $attribute->getFrontend()->getLabel() . "=" . $value;
        }
      }
    }
    if ($this->publishtieredpricing == "1") {
      $TierPriceAttributes = $this->_getTierPrices($product);
      $sp = ( empty($ProductAttributes) || empty($TierPriceAttributes) ) ? "" : "|";
      $ProductAttributes .= $sp . $TierPriceAttributes;
    }
    if ($this->importoptionsasattributes) {
      $optionsAsAttributes = $this->_getCustomOptionsAsAttributes($product);
      if (!empty($optionsAsAttributes)) {
        if (!empty($ProductAttributes)) {
          $ProductAttributes .= "|";
        }
        $ProductAttributes .= $optionsAsAttributes;
      }
    }
    if (!empty($ProductAttributes)) {
      $ProductAttributes = $this->_cleanStr($ProductAttributes);
    }
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("product image: " . $product->getImage() . "
small image: " . $product->getSmallImage() . "
thumbnail: " . $product->getThumbnail() . "
");
    }
    $prodImages = array();
    $firstImageFile = trim($product->getImage());
    if ($firstImageFile == "no_selection") {
      $firstImageFile = FALSE;
    }
    if ($firstImageFile) {
      $prodImages[] = "MagentoBase#=#" . $this->prodmediaurl . $this->_formatImageURL($firstImageFile);
    }
    $prodImageArray = $product->getMediaGallery('images');
    if (is_array($prodImageArray)) {
      foreach ($prodImageArray as $image) {
        $imageFile = trim($image['file']);
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("gallery image: " . $imageFile . " disabled=" . $image['disabled'] . "
");
        }
        if (!$imageFile || $imageFile == $firstImageFile) {
          continue;
        }
        if ($this->ignoreexcludedimages && $image['disabled']) {
          continue;
        }
        $prodImages[] = "Print#=#" . $this->prodmediaurl . $this->_formatImageURL($imageFile);
      }
    }
    $prodImages = implode("#|#", $prodImages);
    $ProductURL = $product->getProductUrl();
    if (!empty($ProductURL)) {
      $ProductURL = $this->_correctProdUrlStr($ProductURL);
    }
    $productName = $product->getName();
    $productName = $product->getId() . '#$#' . $this->_cleanStr($productName);
    $Reviews = $this->enablereviews ? $this->_getReviews($product->getId()) : '';
    if (!empty($Reviews)) {
      $Reviews = $this->_cleanStr($Reviews);
    }
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("associated IDs: ");
      foreach ($AssociatedProductIds as $UsedProductid) {
        $this->controller->getResponse()->appendBody("" . $UsedProductid . " ");
      }
      $this->controller->getResponse()->appendBody("
");
    }
    $sequence = 1;
    $UsedProducts = array();
    foreach ($AssociatedProductIds as $UsedProductid) {
      $UsedProduct = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->load($UsedProductid);
      if ($UsedProduct->getStatus() == 1) {
        $UsedProducts[] = $UsedProduct;
      } else {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("sku=" . $UsedProduct->getSku() . " enabled=" . $UsedProduct->getStatus() . " - bypassed.
");
        }
        unset($UsedProduct);
      }
    }
    $renderSPrice = FALSE;
    if ($this->includespecialprice) {
      foreach ($UsedProducts as $UsedProduct) {
        $SPrice = $UsedProduct->getSpecialPrice();
        if (trim($SPrice) != "") {
          $renderSPrice = TRUE;
          break;
        }
      }
    }
    foreach ($UsedProducts as $UsedProduct) {
      if ($this->instockonly) {
        $inventory = $this->catalogInventoryModel->loadByProduct($UsedProduct);
        $qty = $inventory->getQty();
        $minQty = $inventory->getMinQty();
        if ($qty <= $minQty) {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("sku=" . $UsedProduct->getSku() . " enabled=" . $UsedProduct->getStatus() . " qty=" . $qty . " minQty=" . $minQty . " - bypassing.
");
          }
          continue;
        }
      }
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("sku=" . $UsedProduct->getSku() . " enabled=" . $UsedProduct->getStatus() . " prices: Regular=" . $UsedProduct->getData('price') . " Final=" . $UsedProduct->getFinalPrice() . " Special=" . $UsedProduct->getSpecialPrice() . " FromDate=" . $UsedProduct->getSpecialFromDate() . " ToDate=" . $UsedProduct->getSpecialToDate() . "
");
      }
      $ItemName = $UsedProduct->getName();
      $ProdRPrice = $UsedProduct->getData('price');
      $ProdSPrice = $UsedProduct->getSpecialPrice();
      $ProdSFromDate = $UsedProduct->getSpecialFromDate();
      $ProdSToDate = $UsedProduct->getSpecialToDate();
      $ProdGroupPrices = FALSE;
      if ($this->mageVersionArray['major'] > 1 || $this->mageVersionArray['minor'] > 6) {
        $ProdGroupPrices = $UsedProduct->getData('group_price');
        if ($this->_DEBUG && count($ProdGroupPrices) > 0) {
          $this->controller->getResponse()->appendBody("group_price: " . var_export($ProdGroupPrices, true) . "
");
        }
      }
      $ProdSpecGroupPrices = FALSE;
      if ($ProdGroupPrices) {
        $ProdSpecGroupPrices = array();
        foreach ($ProdGroupPrices as $groupPrice) {
          if ($groupPrice['cust_group'] == '0') {
            $notLoggedInPrice = 0.0 + $groupPrice['price'];
            if (trim($ProdSPrice) == "" || $notLoggedInPrice < 0.0 + $ProdSPrice) {
              $ProdSPrice = $groupPrice['price'];
              $ProdSFromDate = FALSE;
              $ProdSToDate = FALSE;
            }
          } else {
            $ProdSpecGroupPrices[] = $groupPrice;
          }
        }
      }
      if (trim($ProdSPrice) == "") {
        $ProdSPrice = -1;
        $ProdSFromDate = FALSE;
        $ProdSToDate = FALSE;
      }
      $SFromDate = $ProdSFromDate;
      $SToDate = $ProdSToDate;
      $RPrice = $ProdRPrice;
      if (!empty($RPrice) && $this->includetaxes) {
        $RPrice = $this->taxhelper->getPrice($UsedProduct, $RPrice, true, null, null, null, $this->StoreId);
      }
      $SPrice = $ProdSPrice;
      if ($SPrice >= 0 && $this->includetaxes) {
        $SPrice = $this->taxhelper->getPrice($UsedProduct, $SPrice, true, null, null, null, $this->StoreId);
      }
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("resulting prices: Regular=" . $RPrice . " Special=" . $SPrice . " FromDate=" . $SFromDate . " ToDate=" . $SToDate . "
");
      }
      if ($this->ignoreassprodimages) {
        $itemImages = "";
      } else {
        $itemImages = array();
        $firstImageFile = trim($UsedProduct->getImage());
        if ($firstImageFile == "no_selection") {
          $firstImageFile = FALSE;
        }
        if ($firstImageFile) {
          $itemImages[] = "MagentoBase#=#" . $this->prodmediaurl . $this->_formatImageURL($firstImageFile);
        }
        $itemImageArray = $UsedProduct->getMediaGallery('images');
        if (is_array($itemImageArray)) {
          foreach ($itemImageArray as $image) {
            $imageFile = trim($image['file']);
            if (!$imageFile || $imageFile == $firstImageFile) {
              continue;
            }
            if ($this->ignoreexcludedimages && $image['disabled']) {
              continue;
            }
            $itemImages[] = "Print#=#" . $this->prodmediaurl . $this->_formatImageURL($imageFile);
          }
        }
        $itemImages = implode("#|#", $itemImages);
      }
      $attributes = $UsedProduct->getAttributes();
      $ItemAttributes = "";
      foreach ($attributes as $attribute) {
        $attribute->setStoreId($this->StoreId);
        if ($this->_DEBUG && ( $attribute->getFrontendInput() == "select" || $attribute->getFrontendInput() == "multiselect" )) {
          $hasData = $UsedProduct->getData($attribute->getAttributeCode()) ? "YES" : "NO";
          $this->controller->getResponse()->appendBody("grouped prod attr: " . $attribute->getFrontend()->getLabel() . "=" . $attribute->getFrontend()->getValue($UsedProduct) . " input=" . $attribute->getFrontendInput() . " data=" . $UsedProduct->getData($attribute->getAttributeCode()) . " hasData=" . $hasData . "
");
        }
        if ($attribute->getAttributeCode() == "manufacturer") {
          if ($UsedProduct->getData($attribute->getAttributeCode())) {
            $Manufacturer = $attribute->getFrontend()->getValue($UsedProduct);
          }
          continue;
        }
        if ($attribute->getIsVisibleOnFront() ||
          ( $this->includecustomfields && array_key_exists(strtolower($attribute->getAttributeCode()), $this->includecustomfields) )) {
          $value = $attribute->getFrontend()->getValue($UsedProduct);
          if ($attribute->getFrontendInput() == "select" || $attribute->getFrontendInput() == "multiselect") {
            $hasData = $UsedProduct->getData($attribute->getAttributeCode()) ? TRUE : FALSE;
            if (!$hasData) {
              $value = "";
            }
          }
          if (is_string($value) && strlen($value)) {
            $ItemAttributes .= $attribute->getFrontend()->getLabel() . "=" . $value . "|";
          }
        }
      }
      $ItemAttributes = substr($ItemAttributes, 0, strlen($ItemAttributes) - 1);
      if ($this->includeinvqty) {
        if (!empty($ItemAttributes)) {
          $ItemAttributes .= "|";
        }
        $ItemAttributes .= "Inventory=" . $this->_formatQty("" . $this->catalogInventoryModel->loadByProduct($UsedProduct)->getQty());
      }
      if ($this->publishtieredpricing == "1") {
        $TierPriceAttributes = $this->_getTierPrices($UsedProduct);
        $sp = ( empty($ItemAttributes) || empty($TierPriceAttributes) ) ? "" : "|";
        $ItemAttributes .= $sp . $TierPriceAttributes;
      }
      $RPrice = $this->_formatPrice($RPrice);
      if ($this->includespecialprice) {
        if ($SPrice >= 0) {
          $SPrice = $this->_formatPrice($SPrice);
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price=" . $SPrice;
        } else if ($renderSPrice) {
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price=";
        }
      }
      if ($this->includespecialpricedatefrom) {
        if (!empty($SFromDate)) {
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price From Date=" . substr($SFromDate, 0, 10);
        }
      }
      if ($this->includespecialpricedateto) {
        if (!empty($SToDate)) {
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price To Date=" . substr($SToDate, 0, 10);
        }
      }
      if ($this->getgroupprices && $ProdSpecGroupPrices) {
        foreach ($ProdSpecGroupPrices as $groupPrice) {
          $price = 0.0 + $groupPrice['price'];
          if ($price >= 0 && $this->includetaxes) {
            $price = $this->taxhelper->getPrice($UsedProduct, $price, true, null, null, null, $this->StoreId);
          }
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "price_" . $this->customerGroups[$groupPrice['cust_group']] . "=" . $this->_formatPrice($price);
        }
      }
      $itemID = $UsedProduct->getSku();
      $ProducttoString .= $this->_cleanStr($itemID) //ItemID
        . "\t" //ItemQty
        . "\t" //ItemUom
        . "\t" . $RPrice //ItemPrice
        . "\t" . $this->_cleanStr($ItemName) //ItemDescription
        . "\t" //ItemLink
        . "\t" . $this->_cleanStr($ItemAttributes) //ItemAttributes
        . "\t" . $itemImages //ItemGraphic
        . "\t" . $productName //ProductName
        . "\t" . $ProductDescription
        . "\t" . $prodImages //ProductGraphic
        . "\t" . $ProductURL //ProductLink
        . "\t" . $ProductAttributes //ProductAttributes
        . "\t" . $Manufacturer //Manufacturer
        . "\t" //Category
        . "\t" . $Reviews //Reviews
        . "\t" . $shortDescription
        . "\t" . $sequence
        . "\n";
      unset($UsedProduct);
      $sequence++;
    }
    unset($UsedProducts);
    unset($AssociatedProductIds);
    return $ProducttoString;
  }

  private function ProducttoStringConfigurable(&$product) {
    $UsedProductIds = $this->productTypeConfigurableModel->getUsedProductIds($product);
    $ProductDescription = "";
    $shortDescription = "";
    if ($this->includeshortdescription) {
      $shortDescription = $product->getShortDescription();
      if (!empty($shortDescription)) {
        $shortDescription = $this->_cleanStr($shortDescription);
        if (strpos($shortDescription, self::TAG_P) !== false) {
          $new = strlen($shortDescription);
          $pos = strrpos($shortDescription, self::TAG_P_CLOSE) + 4;
          if ($new != $pos) {
            $shortDescription = substr($shortDescription, 0, $pos) . self::TAG_P . substr($shortDescription, $pos) . self::TAG_P_CLOSE;
          }
        } else {
          $shortDescription = self::TAG_P . $shortDescription . self::TAG_P_CLOSE;
        }
        $ProductDescription = $shortDescription;
      }
    }
    if ($this->includelongdescription) {
      $longDescription = $product->getDescription();
      if (!empty($longDescription)) {
        $longDescription = $this->_cleanStr($longDescription);
        if (strpos($longDescription, self::TAG_P) !== false) {
          $new = strlen($longDescription);
          $pos = strrpos($longDescription, self::TAG_P_CLOSE) + 4;
          if ($new != $pos) {
            $longDescription = substr($longDescription, 0, $pos) . self::TAG_P . substr($longDescription, $pos) . self::TAG_P_CLOSE;
          }
        } else {
          $longDescription = self::TAG_P . $longDescription . self::TAG_P_CLOSE;
        }
        $ProductDescription .= $longDescription;
      }
    }
    $attributes = $product->getAttributes();
    $Manufacturer = "";
    $ProductAttributes = "";
    foreach ($attributes as $attribute) {
      $attribute->setStoreId($this->StoreId);
      if ($attribute->getAttributeCode() == "manufacturer") {
        if ($product->getData($attribute->getAttributeCode())) {
          $Manufacturer = $attribute->getFrontend()->getValue($product);
        } else {
          $Manufacturer = "";
        }
        continue;
      }
      if ($attribute->getIsVisibleOnFront() ||
        ( $this->includecustomfields && array_key_exists(strtolower($attribute->getAttributeCode()), $this->includecustomfields) )) {
        $value = $attribute->getFrontend()->getValue($product);
        if ($attribute->getFrontendInput() == "select" || $attribute->getFrontendInput() == "multiselect") {
          $hasData = $product->getData($attribute->getAttributeCode()) ? TRUE : FALSE;
          if (!$hasData) {
            $value = "";
          }
        }
        if (is_string($value) && strlen($value)) {
          if (!empty($ProductAttributes)) {
            $ProductAttributes .= "|";
          }
          $ProductAttributes .= $attribute->getFrontend()->getLabel() . "=" . $value;
        }
      }
    }
    if ($this->publishtieredpricing == "1") {
      $TierPriceAttributes = $this->_getTierPrices($product);
      $sp = ( empty($ProductAttributes) || empty($TierPriceAttributes) ) ? "" : "|";
      $ProductAttributes.= $sp . $TierPriceAttributes;
    }
    if ($this->importoptionsasattributes) {
      $optionsAsAttributes = $this->_getCustomOptionsAsAttributes($product);
      if (!empty($optionsAsAttributes)) {
        if (!empty($ProductAttributes)) {
          $ProductAttributes .= "|";
        }
        $ProductAttributes .= $optionsAsAttributes;
      }
    }
    if (!empty($ProductAttributes)) {
      $ProductAttributes = $this->_cleanStr($ProductAttributes);
    }
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("product image: " . $product->getImage() . "
small image: " . $product->getSmallImage() . "
thumbnail: " . $product->getThumbnail() . "
");
    }
    $prodImages = array();
    $firstImageFile = trim($product->getImage());
    if ($firstImageFile == "no_selection") {
      $firstImageFile = FALSE;
    }
    if ($firstImageFile) {
      $prodImages[] = "MagentoBase#=#" . $this->prodmediaurl . $this->_formatImageURL($firstImageFile);
    }
    $prodImageArray = $product->getMediaGallery('images');
    if (is_array($prodImageArray)) {
      foreach ($prodImageArray as $image) {
        $imageFile = trim($image['file']);
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("gallery image: " . $imageFile . " disabled=" . $image['disabled'] . "
");
        }
        if (!$imageFile || $imageFile == $firstImageFile) {
          continue;
        }
        if ($this->ignoreexcludedimages && $image['disabled']) {
          continue;
        }
        $prodImages[] = "Print#=#" . $this->prodmediaurl . $this->_formatImageURL($imageFile);
      }
    }
    $prodImages = implode("#|#", $prodImages);
    $ProductURL = $product->getProductUrl();
    if (!empty($ProductURL)) {
      $ProductURL = $this->_correctProdUrlStr($ProductURL);
    }
    $productName = $product->getName();
    $productName = $product->getId() . '#$#' . $this->_cleanStr($productName);
    $Reviews = $this->enablereviews ? $this->_getReviews($product->getId()) : '';
    if (!empty($Reviews)) {
      $Reviews = $this->_cleanStr($Reviews);
    }
    if (!$this->getpricefromchild) {
      $ProdRPrice = $product->getData('price');
      $ProdSPrice = $product->getSpecialPrice();
      $ProdSFromDate = $product->getSpecialFromDate();
      $ProdSToDate = $product->getSpecialToDate();
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("prod prices: Regular=" . $ProdRPrice . " Special=" . $ProdSPrice . " FromDate=" . $ProdSFromDate . " ToDate=" . $ProdSToDate . "
");
      }
      $ProdGroupPrices = FALSE;
      if ($this->mageVersionArray['major'] > 1 || $this->mageVersionArray['minor'] > 6) {
        $ProdGroupPrices = $product->getData('group_price');
        if ($this->_DEBUG && count($ProdGroupPrices) > 0) {
          $this->controller->getResponse()->appendBody("group_price: " . var_export($ProdGroupPrices, true) . "
");
        }
      }
      $ProdSpecGroupPrices = FALSE;
      if ($ProdGroupPrices) {
        $ProdSpecGroupPrices = array();
        foreach ($ProdGroupPrices as $groupPrice) {
          if ($groupPrice['cust_group'] == '0') {
            $notLoggedInPrice = 0.0 + $groupPrice['price'];
            if (trim($ProdSPrice) == "" || $notLoggedInPrice < 0.0 + $ProdSPrice) {
              $ProdSPrice = $groupPrice['price'];
              $ProdSFromDate = FALSE;
              $ProdSToDate = FALSE;
            }
          } else {
            $ProdSpecGroupPrices[] = $groupPrice;
          }
        }
      }
      if (trim($ProdSPrice) == "") {
        $ProdSPrice = -1;
        $ProdSFromDate = FALSE;
        $ProdSToDate = FALSE;
      }
    } // ! $this->getpricefromchild
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("used product IDs: ");
      foreach ($UsedProductIds as $UsedProductid) {
        $this->controller->getResponse()->appendBody("" . $UsedProductid . " ");
      }
      $this->controller->getResponse()->appendBody("
");
    }
    $usedConfigurableAttributes = FALSE;
    if ($this->importoptionsasattributes) {
      $usedConfigurableAttributes = array();
    }
    $ConfigurableAttributes = $product->getTypeInstance(true)->getConfigurableAttributes($product);
    foreach ($ConfigurableAttributes as $attribute) {
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("configurable attr: " . $attribute->getLabel() . "
");
      }
      $configurableProdAttribute = $attribute->getProductAttribute();
      if (empty($configurableProdAttribute)) {
        $label = $attribute->getLabel();
        $this->controller->getResponse()->appendBody("WARNING: no product attribute for configurable attribute " . $label . "
");
        continue;
      }
      $configurableProdAttribute->setStoreId($this->StoreId);
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("configurable prod attr: " . $configurableProdAttribute->getFrontend()->getLabel() . "
");
      }
      $AttributeLabel = $attribute->getLabel();
      if (!$AttributeLabel) {
        $AttributeLabel = $configurableProdAttribute->getFrontend()->getLabel();
      }
      if ($this->importoptionsasattributes) {
        $usedConfigurableAttributes[$AttributeLabel] = array();
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("created usedConfigurableAttributes array for \"" . $AttributeLabel . "\"
");
        }
      }
      foreach ($configurableProdAttribute->getSource()->getAllOptions() as $option) {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("variation option: " . $option['value'] . "=>" . $option['label'] . "
");
        }
        if ($this->importoptionsasattributes) {
          $usedConfigurableAttributes[$AttributeLabel][$option['label']] = FALSE;
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("created usedConfigurableAttributes label \"" . $option['label'] . "\"
");
          }
        }
      }
    }
    $UsedProducts = array();
    foreach ($UsedProductIds as $UsedProductid) {
      $UsedProduct = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->load($UsedProductid);
      if ($UsedProduct->getStatus() == 1) {
        $UsedProducts[$UsedProductid] = $UsedProduct;
      } else {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("sku=" . $UsedProduct->getSku() . " enabled=" . $UsedProduct->getStatus() . " - bypassed.
");
        }
        unset($UsedProduct);
      }
    }
    $cpLines = array();
    foreach ($UsedProducts as $UsedProductid => $UsedProduct) {
      $cpLine = new ConfiguredProductLine();
      if ($this->instockonly) {
        $inventory = $this->catalogInventoryModel->loadByProduct($UsedProduct);
        $qty = $inventory->getQty();
        $minQty = $inventory->getMinQty();
        if ($qty <= $minQty) {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("sku=" . $UsedProduct->getSku() . " qty=" . $qty . " minQty=" . $minQty . " - bypassing.
");
          }
          continue;
        }
      }
      if (!$this->getpricefromchild) {
        $RPrice = $ProdRPrice;
        $SPrice = $ProdSPrice;
        $RPrice0 = $ProdRPrice;
        $SPrice0 = $ProdSPrice;
        $SFromDate = !empty($SPrice) ? $ProdSFromDate : FALSE;
        $SToDate = !empty($SPrice) ? $ProdSToDate : FALSE;
        $SpecGroupPrices = FALSE;
        $SpecGroupPrices0 = FALSE;
        if ($ProdSpecGroupPrices) {
          $SpecGroupPrices = array();
          $SpecGroupPrices0 = array();
          foreach ($ProdSpecGroupPrices as $prodGroupPrice) {
            $SpecGroupPrices[$prodGroupPrice['cust_group']] = 0.0 + $prodGroupPrice['price'];
            $SpecGroupPrices0[$prodGroupPrice['cust_group']] = 0.0 + $prodGroupPrice['price'];
          }
        }
      } else { // $this->getpricefromchild
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("sku=" . $UsedProduct->getSku() . " prices: Regular=" . $UsedProduct->getData('price') . " Final=" . $UsedProduct->getFinalPrice() . " Special=" . $UsedProduct->getSpecialPrice() . " FromDate=" . $UsedProduct->getSpecialFromDate() . " ToDate=" . $UsedProduct->getSpecialToDate() . "
");
        }
        $RPrice = $UsedProduct->getData('price');
        $SPrice = $UsedProduct->getSpecialPrice();
        $SFromDate = $UsedProduct->getSpecialFromDate();
        $SToDate = $UsedProduct->getSpecialToDate();
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("prod prices: Regular=" . $RPrice . " Special=" . $SPrice . " FromDate=" . $SFromDate . " ToDate=" . $SToDate . "
");
        }
        $ProdGroupPrices = FALSE;
        if ($this->mageVersionArray['major'] > 1 || $this->mageVersionArray['minor'] > 6) {
          $ProdGroupPrices = $UsedProduct->getData('group_price');
          if ($this->_DEBUG && count($ProdGroupPrices) > 0) {
            $this->controller->getResponse()->appendBody("group_price: " . var_export($ProdGroupPrices, true) . "
");
          }
        }
        $SpecGroupPrices = FALSE;
        $SpecGroupPrices0 = FALSE;
        if ($ProdGroupPrices) {
          $SpecGroupPrices = array();
          foreach ($ProdGroupPrices as $groupPrice) {
            if ($groupPrice['cust_group'] == '0') {
              $notLoggedInPrice = 0.0 + $groupPrice['price'];
              if (trim($SPrice) == "" || $notLoggedInPrice < 0.0 + $SPrice) {
                $SPrice = $groupPrice['price'];
                $SFromDate = FALSE;
                $SToDate = FALSE;
              }
            } else {
              $SpecGroupPrices[] = $groupPrice;
            }
          }
        }
        if (trim($SPrice) == "") {
          $SPrice = -1;
          $SFromDate = FALSE;
          $SToDate = FALSE;
        }
      }
      $ItemAttributes = "";
      foreach ($ConfigurableAttributes as $attribute) {
        $configurableProdAttribute = $attribute->getProductAttribute();
        if (empty($configurableProdAttribute)) {
          $label = $attribute->getLabel();
          $this->controller->getResponse()->appendBody("WARNING: no product attribute for configurable attribute " . $label . "
");
          $cpLine->optionIds[] = 0;
          continue;
        }
        $AttributeCode = $configurableProdAttribute->getAttributeCode();
        $AttributeLabel = $attribute->getLabel();
        if (!$AttributeLabel) {
          $AttributeLabel = $configurableProdAttribute->getFrontend()->getLabel();
        }
        $AttribId = $UsedProduct->getData($AttributeCode);
        $cpLine->optionIds[] = intVal($AttribId);
        $AttributeValue = "";
        foreach ($configurableProdAttribute->getSource()->getAllOptions() as $option) {
          if ($option['value'] == $AttribId) {
            $AttributeValue = $option['label'];
            break;
          }
        }
        if ($this->importoptionsasattributes && trim($AttributeValue)) {
          $usedConfigurableAttributes[$AttributeLabel][$AttributeValue] = TRUE;
        }
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("config attr code=" . $AttributeCode . " label=" . $AttributeLabel . " id=" . $AttribId . " value=" . $AttributeValue . "
");
        }
        if (!empty($ItemAttributes)) {
          $ItemAttributes .= "|";
        }
        $ItemAttributes .= $AttributeLabel . "=" . $AttributeValue;
        if (!$this->getpricefromchild) {
          foreach ($attribute->getPrices() as $addedPrice) {
            if ($AttributeValue == $addedPrice['label']) {
              if ($addedPrice['is_percent']) {
                $RPrice += round($RPrice0 * $addedPrice['pricing_value'] / 100, 2);
                if ($SPrice >= 0) {
                  $SPrice += round($SPrice0 * $addedPrice['pricing_value'] / 100, 2);
                }
                if ($SpecGroupPrices) {
                  foreach ($SpecGroupPrices0 as $key => $value) {
                    $SpecGroupPrices[$key] += round($value * $addedPrice['pricing_value'] / 100, 2);
                  }
                }
              } else {
                $RPrice += $addedPrice['pricing_value'];
                if ($SPrice >= 0) {
                  $SPrice += $addedPrice['pricing_value'];
                }
                if ($SpecGroupPrices) {
                  foreach ($SpecGroupPrices0 as $key => $value) {
                    $SpecGroupPrices[$key] += $addedPrice['pricing_value'];
                  }
                }
              }
            }
          }
        } // ! $this->getpricefromchild
      }
      if (!empty($RPrice) && $this->includetaxes) {
        $RPrice = $this->taxhelper->getPrice($UsedProduct, $RPrice, true, null, null, null, $this->StoreId);
      }
      if (!empty($SPrice) && $this->includetaxes) {
        $SPrice = $this->taxhelper->getPrice($UsedProduct, $SPrice, true, null, null, null, $this->StoreId);
      }
      if ($this->includeinvqty) {
        if (!empty($ItemAttributes)) {
          $ItemAttributes .= "|";
        }
        $ItemAttributes .= "Inventory=" . $this->_formatQty("" . $this->catalogInventoryModel->loadByProduct($UsedProduct)->getQty());
      }
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("resulting prices: Regular=" . $RPrice . " Special=" . $SPrice . " FromDate=" . $SFromDate . " ToDate=" . $SToDate . "
");
      }
      $RPrice = $this->_formatPrice($RPrice);
      if ($this->includespecialprice) {
        if ($SPrice >= 0) {
          $SPrice = $this->_formatPrice($SPrice);
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price=" . $SPrice;
        }
      }
      if ($this->includespecialpricedatefrom) {
        if (!empty($SFromDate)) {
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price From Date=" . substr($SFromDate, 0, 10);
        }
      }
      if ($this->includespecialpricedateto) {
        if (!empty($SToDate)) {
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price To Date=" . substr($SToDate, 0, 10);
        }
      }
      if ($this->getgroupprices && $SpecGroupPrices) {
        foreach ($SpecGroupPrices as $groupID => $price) {
          if ($price >= 0 && $this->includetaxes) {
            $price = $this->taxhelper->getPrice($UsedProduct, $price, true, null, null, null, $this->StoreId);
          }
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "price_" . $this->customerGroups[$groupID] . "=" . $this->_formatPrice($price);
        }
      }
      if (!$this->getpricefromchild) {
        if ($this->publishtieredpricing == "1") {
          $TierPriceAttributes = $this->_getTierPrices($UsedProduct);
          $sp = ( empty($ItemAttributes) || empty($TierPriceAttributes) ) ? "" : "|";
          $ItemAttributes.= $sp . $TierPriceAttributes;
        }
      } // ! $this->getpricefromchild
      if ($this->ignoreassprodimages) {
        $itemImages = "";
      } else {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("config item image: " . $UsedProduct->getImage() . "
small image: " . $UsedProduct->getSmallImage() . "
thumbnail: " . $UsedProduct->getThumbnail() . "
");
        }
        $itemImages = array();
        $firstImageFile = trim($UsedProduct->getImage());
        if ($firstImageFile == "no_selection") {
          $firstImageFile = FALSE;
        }
        if ($firstImageFile) {
          $itemImages[] = "MagentoBase#=#" . $this->prodmediaurl . $this->_formatImageURL($firstImageFile);
        }
        $itemImageArray = $UsedProduct->getMediaGallery('images');
        if (is_array($itemImageArray)) {
          foreach ($itemImageArray as $image) {
            $imageFile = trim($image['file']);
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody("gallery image: " . $imageFile . " disabled=" . $image['disabled'] . "
");
            }
            if (!$imageFile || $imageFile == $firstImageFile) {
              continue;
            }
            if ($this->ignoreexcludedimages && $image['disabled']) {
              continue;
            }
            $itemImages[] = "Print#=#" . $this->prodmediaurl . $this->_formatImageURL($imageFile);
          }
        }
        $itemImages = implode("#|#", $itemImages);
      }
      $itemID = $UsedProduct->getSku();
      $cpLine->line = $this->_cleanStr($itemID) //ItemID
        . "\t" //ItemQty
        . "\t" //ItemUom
        . "\t" . $RPrice //ItemPrice
        . "\t" //ItemDescription
        . "\t" //ItemLink
        . "\t" . $this->_cleanStr($ItemAttributes) //ItemAttributes
        . "\t" . $itemImages; //ItemitemGraphic
      $cpLines[] = $cpLine;
      unset($UsedProduct);
    }
    unset($UsedProducts);
    unset($UsedProductIds);
    $optionIds = array();
    $attrNumber = 0;
    $optNumbers = array();
    $optCounters = array();
    foreach ($ConfigurableAttributes as $attribute) {
      $configurableProdAttribute = $attribute->getProductAttribute();
      if (empty($configurableProdAttribute)) {
        continue;
      }
      $optionIdList = array();
      $optionNo = 0;
      foreach ($configurableProdAttribute->getSource()->getAllOptions() as $option) {
        $optionIdList[] = trim($option['value']) == "" ? 0 : intVal($option['value']);
        $optionNo++;
      }
      $optionIds[] = $optionIdList;
      $optCounters[] = $optionNo;
      $optNumbers[] = 0;
      $attrNumber++;
    }
    // supplement prod attrs
    if ($this->importoptionsasattributes) {
      foreach ($usedConfigurableAttributes as $attrName => $attrValues) {
        if (!empty($ProductAttributes)) {
          $ProductAttributes .= "|";
        }
        $ProductAttributes .= $attrName . "=";
        $valCount = 0;
        foreach ($attrValues as $attrValue => $used) {
          if ($used) {
            if ($valCount > 0) {
              $ProductAttributes .= "<br />";
            }
            $ProductAttributes .= $attrValue;
            $valCount++;
          }
        }
      }
    }
    // build prod part
    $this->prodLine = "\t" . $productName //ProductName
      . "\t" . $ProductDescription
      . "\t" . $prodImages //ProductGraphic
      . "\t" . $ProductURL //ProductLink
      . "\t" . $ProductAttributes //ProductAttributes
      . "\t" . $Manufacturer //Manufacturer
      . "\t" //Category
      . "\t" . $Reviews //Reviews
      . "\t" . $shortDescription
      . "\t";
    // build cplines matrix
    $cpLinesMatrix = array();
    foreach ($cpLines as $cpLine) {
      $optionId = $cpLine->optionIds[0];
      if ($attrNumber > 1) {
        if (!isset($cpLinesMatrix[$optionId])) {
          $cpLinesMatrix[$optionId] = array();
        }
        $this->_fillCPLinesMatrix(1, $attrNumber, $cpLine, $cpLinesMatrix[$optionId]);
      } else {
        if (!isset($cpLinesMatrix[$optionId])) {
          $cpLinesMatrix[$optionId] = $cpLine;
        }
      }
    }
    $this->configItemSequence = 1;
    $this->configProducttoString = "";
    $this->_fillConfigProdItems(0, $attrNumber, $optNumbers, $optionIds, $optCounters, $cpLinesMatrix);
    unset($optionIds);
    foreach ($cpLines as $cpLine) {
      if ($cpLine->line) {
        $this->controller->getResponse()->appendBody("WARNING: not rendered config line: " . var_export($cpLine->optionIds, true) . "
");
      }
    }
    unset($cpLines);
    return $this->configProducttoString;
  }

  private function ProducttoStringSimple(&$product) {
    $ProductDescription = "";
    $shortDescription = "";
    if ($this->includeshortdescription) {
      $shortDescription = $product->getShortDescription();
      if (!empty($shortDescription)) {
        $shortDescription = $this->_cleanStr($shortDescription);
        if (strpos($shortDescription, self::TAG_P) !== false) {
          $new = strlen($shortDescription);
          $pos = strrpos($shortDescription, self::TAG_P_CLOSE) + 4;
          if ($new != $pos) {
            $shortDescription = substr($shortDescription, 0, $pos) . self::TAG_P . substr($shortDescription, $pos) . self::TAG_P_CLOSE;
          }
        } else {
          $shortDescription = self::TAG_P . $shortDescription . self::TAG_P_CLOSE;
        }
        $ProductDescription = $shortDescription;
      }
    }
    if ($this->includelongdescription) {
      $longDescription = $product->getDescription();
      if (!empty($longDescription)) {
        $longDescription = $this->_cleanStr($longDescription);
        if (strpos($longDescription, self::TAG_P) !== false) {
          $new = strlen($longDescription);
          $pos = strrpos($longDescription, self::TAG_P_CLOSE) + 4;
          if ($new != $pos) {
            $longDescription = substr($longDescription, 0, $pos) . self::TAG_P . substr($longDescription, $pos) . self::TAG_P_CLOSE;
          }
        } else {
          $longDescription = self::TAG_P . $longDescription . self::TAG_P_CLOSE;
        }
        $ProductDescription .= $longDescription;
      }
    }
    $prodImages = array();
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("product image: " . $product->getImage() . "
small image: " . $product->getSmallImage() . "
thumbnail: " . $product->getThumbnail() . "
");
    }
    $firstImageFile = trim($product->getImage());
    if ($firstImageFile == "no_selection") {
      $firstImageFile = FALSE;
    }
    if ($firstImageFile) {
      $prodImages[] = "MagentoBase#=#" . $this->prodmediaurl . $this->_formatImageURL($firstImageFile);
    }
    $prodImageArray = $product->getMediaGallery('images');
    if (is_array($prodImageArray)) {
      foreach ($prodImageArray as $image) {
        $imageFile = trim($image['file']);
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("gallery image: " . $imageFile . " disabled=" . $image['disabled'] . "
");
        }
        if (!$imageFile || $imageFile == $firstImageFile) {
          continue;
        }
        if ($this->ignoreexcludedimages && $image['disabled']) {
          continue;
        }
        $prodImages[] = "Print#=#" . $this->prodmediaurl . $this->_formatImageURL($imageFile);
      }
    }
    $prodImages = implode("#|#", $prodImages);
    $URL = $product->getProductUrl();
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("URL= " . $URL . "
");
    }
    if (!empty($URL)) {
      $URL = $this->_correctProdUrlStr($URL);
    }
    $attributes = $product->getAttributes();
    $Manufacturer = "";
    $ProductAttributes = "";
    foreach ($attributes as $attribute) {
      $attribute->setStoreId($this->StoreId);
      if ($this->_DEBUG && ( $attribute->getFrontendInput() == "select" || $attribute->getFrontendInput() == "multiselect" )) {
        $hasData = $product->getData($attribute->getAttributeCode()) ? "YES" : "NO";
        $vof = $attribute->getIsVisibleOnFront() ? "YES" : "NO";
        $this->controller->getResponse()->appendBody("simple prod attr: " . $attribute->getFrontend()->getLabel() . "=" . $attribute->getFrontend()->getValue($product) . " input=" . $attribute->getFrontendInput() . " data=" . $product->getData($attribute->getAttributeCode()) . " hasData=" . $hasData . " store=" . $product->_storeId . " vof=" . $vof . "
");
      }
      if ($attribute->getAttributeCode() == "manufacturer") {
        if ($product->getData($attribute->getAttributeCode())) {
          $Manufacturer = $attribute->getFrontend()->getValue($product);
        } else {
          $Manufacturer = "";
        }
        continue;
      }
      if ($attribute->getIsVisibleOnFront() ||
        ( $this->includecustomfields && array_key_exists(strtolower($attribute->getAttributeCode()), $this->includecustomfields) )) {
        $value = $attribute->getFrontend()->getValue($product);
        if ($attribute->getFrontendInput() == "select" || $attribute->getFrontendInput() == "multiselect") {
          $hasData = $product->getData($attribute->getAttributeCode()) ? TRUE : FALSE;
          if (!$hasData) {
            $value = "";
          }
        }
        if (is_string($value) && strlen($value)) {
          if (!empty($ProductAttributes)) {
            $ProductAttributes .= "|";
          }
          $ProductAttributes .= $attribute->getFrontend()->getLabel() . "=" . $value;
        } else {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("not a string with length > 0
");
          }
        }
      }
    }
    if ($this->publishtieredpricing == "1") {
      $TierPriceAttributes = $this->_getTierPrices($product);
      $sp = ( empty($ProductAttributes) || empty($TierPriceAttributes) ) ? "" : "|";
      $ProductAttributes.= $sp . $TierPriceAttributes;
    }
    if ($this->importoptionsasattributes) {
      $optionsAsAttributes = $this->_getCustomOptionsAsAttributes($product);
      if (!empty($optionsAsAttributes)) {
        if (!empty($ProductAttributes)) {
          $ProductAttributes .= "|";
        }
        $ProductAttributes .= $optionsAsAttributes;
      }
    }
    if (!empty($ProductAttributes)) {
      $ProductAttributes = $this->_cleanStr($ProductAttributes);
    }
    $ProdRPrice = $product->getData('price');
    $ProdSPrice = $product->getSpecialPrice();
    $ProdSFromDate = $product->getSpecialFromDate();
    $ProdSToDate = $product->getSpecialToDate();
    if ($this->instockonly) {
      $inventory = $this->catalogInventoryModel->loadByProduct($product);
      $qty = $inventory->getQty();
      $minQty = $inventory->getMinQty();
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("sku=" . $product->getSku() . " qty=" . $qty . " minQty=" . $minQty . "
");
      }
      if ($qty <= $minQty) {
        return;
      }
    }
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("sku=" . $product->getSku() . " prices: Regular=" . $ProdRPrice . " Special=" . $ProdSPrice . " FromDate=" . $ProdSFromDate . " ToDate=" . $ProdSToDate . "
");
    }
    $ProdGroupPrices = FALSE;
    if ($this->mageVersionArray['major'] > 1 || $this->mageVersionArray['minor'] > 6) {
      $ProdGroupPrices = $product->getData('group_price');
      if ($this->_DEBUG && count($ProdGroupPrices) > 0) {
        $this->controller->getResponse()->appendBody("group_price: " . var_export($ProdGroupPrices, true) . "
");
      }
    }
    $ProdSpecGroupPrices = FALSE;
    if ($ProdGroupPrices) {
      $ProdSpecGroupPrices = array();
      foreach ($ProdGroupPrices as $groupPrice) {
        if ($groupPrice['cust_group'] == '0') {
          $notLoggedInPrice = 0.0 + $groupPrice['price'];
          if (trim($ProdSPrice) == "" || $notLoggedInPrice < 0.0 + $ProdSPrice) {
            $ProdSPrice = $groupPrice['price'];
            $ProdSFromDate = FALSE;
            $ProdSToDate = FALSE;
          }
        } else {
          $ProdSpecGroupPrices[] = $groupPrice;
        }
      }
    }
    if (trim($ProdSPrice) == "") {
      $ProdSPrice = -1;
      $ProdSFromDate = FALSE;
      $ProdSToDate = FALSE;
    }
    if (!$ProdRPrice && $product->getTypeId() == "bundle") {
      $priceModel = $product->getPriceModel();
      $options = Mage::getSingleton('core/layout')->createBlock('bundle/catalog_product_view_type_bundle')->setProduct($product)->getOptions();
      $ProdRPrice = 0;
      foreach ($options as $option) {
        $selection = $option->getDefaultSelection();
        $MinSelPrice = 0;
        if ($selection) {
          $MinSelPrice = $priceModel->getSelectionPreFinalPrice($product, $selection, $selection->getSelectionQty());
        } else if (!$selection && $option->_origData["required"] == "1") {
          foreach ($option->getSelections() as $selection) {
            $SelPrice = $priceModel->getSelectionPreFinalPrice($product, $selection, $selection->getSelectionQty());
            if (( $MinSelPrice == 0 && $SelPrice > 0 ) || ( $MinSelPrice > 0 && $SelPrice > 0 && $SelPrice < $MinSelPrice )) {
              $MinSelPrice = $SelPrice;
            }
          }
        }
        if ($MinSelPrice <= 0) {
          continue;
        }
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("added " . $MinSelPrice . "
");
        }
        $ProdRPrice += $MinSelPrice;
      }
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("Bundle=" . $ProdRPrice . "
");
      }
    }
    $Reviews = $this->enablereviews ? $this->_getReviews($product->getId()) : '';
    if (!empty($Reviews)) {
      $Reviews = $this->_cleanStr($Reviews);
    }
    $prodSku = $product->getSku();
    $prodSku = $this->_cleanStr($prodSku);
    $cleanProdName = $this->_cleanStr($prodName);
    $prodName = $product->getName();
    $cleanProdName = $this->_cleanStr($prodName);
    $prodName = $product->getId() . '#$#' . $cleanProdName;
    $skusAdded = false;
    if ($this->importoptionsassku) {
      $this->configItemSequence = 1;
      $this->configProducttoString = "";
      $customOptions = $product->getOptions();
      if ($customOptions) {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("=== customOptions ===
ProdRPrice=" . $ProdRPrice . " ProdSPrice=" . $ProdSPrice . "
");
        }
        $max_sort_order = 0;
        foreach ($customOptions as $option) {
          if ($option->getSortOrder() > $max_sort_order) {
            $max_sort_order = $option->getSortOrder();
          } else if ($option->getSortOrder() < 0) {
            $option->setSortOrder(-1);
          }
        }
        $orderedOptionValues = array();
        $orderedCustomOptions = array();
        for ($sortorder = -1; $sortorder <= $max_sort_order; $sortorder++) {
          foreach ($customOptions as $option) {
            if ($option->getSortOrder() != $sortorder) {
              continue;
            }
            $orderedCustomOptions[] = $option;
            $optionValues = array();
            if ($option->getValues()) {
              $max_v_sort_order = 0;
              foreach ($option->getValues() as $_value) {
                if ($_value->getSortOrder() > $max_v_sort_order) {
                  $max_v_sort_order = $_value->getSortOrder();
                } else if ($_value->getSortOrder() < 0) {
                  $_value->setSortOrder(-1);
                }
              }
              for ($vsortorder = -1; $vsortorder <= $max_v_sort_order; $vsortorder++) {
                foreach ($option->getValues() as $_value) {
                  if ($_value->getSortOrder() != $vsortorder) {
                    continue;
                  }
                  $optionValues[] = $_value;
                }
              }
            }
            $orderedOptionValues[$option->getOptionId()] = $optionValues;
          }
        }
        $customOptions = array();
        $customOptionsById = array();
        foreach ($orderedCustomOptions as $option) {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("customOption #" . $option->getOptionId() . " title=" . $option->getTitle() . " storeTitle=" . $option->getStoreTitle() . " type=" . $option->getType() . " isRequire=" . $option->getIsRequire() . " sort_order" . $option->getSortOrder() . "
");
            if ($option->getType() == "date" ||
              $option->getType() == "time" ||
              $option->getType() == "date_time" ||
              $option->getType() == "field" ||
              $option->getType() == "area") {
              $this->controller->getResponse()->appendBody("    price=" . $option->getPrice() . " price_type=" . $option->getPriceType() . " sku=" . $option->getSku() . "
");
            }
          }
          $customOption = new COption();
          $customOption->id = $option->getOptionId();
          $customOption->title = $option->getStoreTitle();
          if (!$customOption->title) {
            $customOption->title = $option->getTitle();
          }
          if (!$customOption->title) {
            $customOption->title = "Option #" . $option->getOptionId();
          }
          $customOption->type = $option->getType();
          $customOption->required = $option->getIsRequire();
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody($customOption->toString() . "
");
          }
          $optionValues = $orderedOptionValues[$option->getOptionId()];
          $isMultiple = ( $option->getType() == "multiple" || $option->getType() == "checkbox" );
          if (!$option->getIsRequire() && !$isMultiple) {
            $coValue = new COValue();
            $coValue->title = FALSE;
            $coValue->sku = FALSE;
            $coValue->ifpercent = FALSE;
            $coValue->pvalue = 0;
            $coValue->rprice = 0;
            $coValue->sprice = 0;
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody($coValue->toString() . "
");
            }
            $customOption->values[] = $coValue;
          }
          if ($option->getType() == "date" ||
            $option->getType() == "time" ||
            $option->getType() == "date_time" ||
            $option->getType() == "field" ||
            $option->getType() == "area") {
            if ($option->getSku()) {
              $coValue = new COValue();
              $coValue->title = "Provided";
              $coValue->sku = $option->getSku();
              $coValue->ifpercent = FALSE;
              $coValue->pvalue = $option->getPrice();
              if ($option->getPriceType() == "percent") {
                $coValue->ifpercent = TRUE;
                $coValue->rprice = round($ProdRPrice * $option->getPrice() / 100, 2);
                $coValue->sprice = $ProdSPrice > -1 ? round($ProdSPrice * $option->getPrice() / 100, 2) : -1;
              } else {
                $coValue->rprice = $option->getPrice();
                $coValue->sprice = $ProdSPrice > -1 ? $option->getPrice() : -1;
              }
              if ($this->_DEBUG) {
                $this->controller->getResponse()->appendBody($coValue->toString() . "
");
              }
              $customOption->values[] = $coValue;
            }
          } else {
            foreach ($optionValues as $optionValue) {
              if ($this->_DEBUG) {
                $this->controller->getResponse()->appendBody("value title=" . $optionValue->getTitle() . " storeTitle=" . $optionValue->getStoreTitle() . " price=" . $optionValue->getPrice() . " price_type=" . $optionValue->getPriceType() . " sku=" . $optionValue->getSku() . " sort_order=" . $optionValue->getSortOrder() . "
");
              }
              if ($optionValue->getSku()) {
                $coValue = new COValue();
                $coValue->title = $optionValue->getStoreTitle();
                if (!$coValue->title) {
                  $coValue->title = $optionValue->getTitle();
                }
                if (!$coValue->title) {
                  $coValue->title = "Option value";
                }
                $coValue->sku = $optionValue->getSku();
                $coValue->ifpercent = FALSE;
                $coValue->pvalue = $optionValue->getPrice();
                if ($optionValue->getPriceType() == "percent") {
                  $coValue->ifpercent = TRUE;
                  $coValue->rprice = round($ProdRPrice * $optionValue->getPrice() / 100, 2);
                  $coValue->sprice = $ProdSPrice > -1 ? round($ProdSPrice * $optionValue->getPrice() / 100, 2) : -1;
                } else {
                  $coValue->rprice = $optionValue->getPrice();
                  $coValue->sprice = $ProdSPrice > -1 ? $optionValue->getPrice() : -1;
                }
                if ($this->_DEBUG) {
                  $this->controller->getResponse()->appendBody($coValue->toString() . "
");
                }
                $customOption->values[] = $coValue;
              }
            }
          }
          if ($isMultiple && count($customOption->values) > 1) {
            $customOption->values = array_reverse($customOption->values);
          }
          if (count($customOption->values) == 0) {
            // OK
          } else if (count($customOption->values) == 1) {
            if ($customOption->values[0]->sku) {
              $customOptions[] = $customOption;
            }
          } else {
            $customOptions[] = $customOption;
          }
          $customOptionsById[$customOption->id] = $customOption;
        }
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("Resulting options / values
");
          foreach ($customOptions as $option) {
            $this->controller->getResponse()->appendBody($option->toString() . "
");
            foreach ($option->values as $value) {
              $this->controller->getResponse()->appendBody($value->toString() . "
");
            }
          }
        }
        if (count($customOptions) > 0) {
          $coValueSetSequences = array();
          $coValueSetSequence = array();
          $this->_buildCOValueSets($coValueSetSequences, $coValueSetSequence, 0, $customOptions);
          $index = 1;
          foreach ($coValueSetSequences as $coValueSetSequence) {
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody("coValueSetSequence #" . $index . "
");
              foreach ($coValueSetSequence as $coValueSet) {
                $this->controller->getResponse()->appendBody($coValueSet->toString() . "
");
              }
            }
            $ItemNumber = $prodSku;
            $RPrice = $ProdRPrice;
            $SPrice = $ProdSPrice;
            $ItemAttributes = "";
            if ($this->includeinvqty) {
              if (!empty($ItemAttributes)) {
                $ItemAttributes .= "|";
              }
              $ItemAttributes = "Inventory=" . $this->_formatQty("" . $this->catalogInventoryModel->loadByProduct($product)->getQty());
            }
            foreach ($coValueSetSequence as $coValueSet) {
              $cOption = $customOptionsById[$coValueSet->optionId];
              if (!empty($ItemAttributes)) {
                $ItemAttributes .= "|";
              }
              $ItemAttributes .= $cOption->title . "=";
              $vcount = 0;
              foreach ($coValueSet->coValues as $value) {
                if ($value->sku) {
                  if ($ItemNumber) {
                    $ItemNumber .= "-";
                  }
                  $ItemNumber .= $value->sku;
                  $RPrice += $value->rprice;
                  if ($SPrice >= 0) {
                    $SPrice += $value->sprice;
                  }
                  if ($vcount > 0) {
                    $ItemAttributes .= ", ";
                  }
                  $ItemAttributes .= $value->title;
                  $vcount++;
                }
              }
            }
            if (empty($ItemNumber)) {
              if ($this->_DEBUG) {
                $this->controller->getResponse()->appendBody("NO ItemNumber
");
              }
              continue;
            }
            if (!empty($RPrice) && $this->includetaxes) {
              $RPrice = $this->taxhelper->getPrice($product, $RPrice, true, null, null, null, $this->StoreId);
            }
            if ($SPrice >= 0 && $this->includetaxes) {
              $SPrice = $this->taxhelper->getPrice($product, $SPrice, true, null, null, null, $this->StoreId);
            }
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody("resulting prices: Regular=" . $RPrice . " Special=" . $SPrice . " FromDate=" . $ProdSFromDate . " ToDate=" . $ProdSToDate . "
");
            }
            $RPrice = $this->_formatPrice($RPrice);
            if ($this->includespecialprice) {
              if ($SPrice >= 0) {
                $SPrice = $this->_formatPrice($SPrice);
                if (!empty($ItemAttributes)) {
                  $ItemAttributes .= "|";
                }
                $ItemAttributes .= "Special Price=" . $SPrice;
              }
            }
            if ($this->includespecialpricedatefrom) {
              if (!empty($ProdSFromDate)) {
                if (!empty($ItemAttributes)) {
                  $ItemAttributes .= "|";
                }
                $ItemAttributes .= "Special Price From Date=" . substr($ProdSFromDate, 0, 10);
              }
            }
            if ($this->includespecialpricedateto) {
              if (!empty($ProdSToDate)) {
                if (!empty($ItemAttributes)) {
                  $ItemAttributes .= "|";
                }
                $ItemAttributes .= "Special Price To Date=" . substr($ProdSToDate, 0, 10);
              }
            }
            if ($this->getgroupprices && $ProdSpecGroupPrices) {
              foreach ($ProdSpecGroupPrices as $groupPrice) {
                $price = 0.0 + $groupPrice['price'];
                $price0 = $price;
                foreach ($coValueSetSequence as $coValueSet) {
                  foreach ($coValueSet->coValues as $value) {
                    if ($value->sku) {
                      if ($value->ifpercent) {
                        $price = $price + round($price0 * $value->pvalue / 100, 2);
                      } else {
                        $price = $price + $value->pvalue;
                      }
                    }
                  }
                }
                if ($price >= 0 && $this->includetaxes) {
                  $price = $this->taxhelper->getPrice($product, $price, true, null, null, null, $this->StoreId);
                }
                if (!empty($ItemAttributes)) {
                  $ItemAttributes .= "|";
                }
                $ItemAttributes .= "price_" . $this->customerGroups[$groupPrice['cust_group']] . "=" . $this->_formatPrice($price);
              }
            }
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody("resulting attrs: " . $ItemAttributes . "
");
            }
            $ProducttoString .= $ItemNumber //ItemID
              . "\t" //ItemQty
              . "\t" //ItemUom
              . "\t" . $RPrice //ItemPrice
              . "\t" //ItemDescription
              . "\t" //ItemLink
              . "\t" . $ItemAttributes //ItemAttributes
              . "\t" //ItemitemGraphic
              . "\t" . $prodName //ProductName
              . "\t" . $ProductDescription //ProductDescription
              . "\t" . $prodImages //ProductGraphic
              . "\t" . $URL //ProductLink
              . "\t" . $ProductAttributes //ProductAttributes
              . "\t" . $Manufacturer //Manufacturer
              . "\t" //Category
              . "\t" . $Reviews //Reviews
              . "\t" . $shortDescription
              . "\t" . $index
              . "\n";
            $skusAdded = TRUE;
            // https://objectpublisher.basecamphq.com/projects/257020-odp/posts/87233819/comments#comment_312388129
            if ($index >= 200) {
              $ProducttoString .= "REPORTWARNING: Not all options were converted to items for the product #" . $product->getId() . " - \"" . $cleanProdName . "\".
";
              break;
            }
            $index++;
          }
        }
      }
    }
    if (!$skusAdded) {
      $RPrice = $ProdRPrice;
      if (!empty($RPrice) && $this->includetaxes) {
        $RPrice = $this->taxhelper->getPrice($product, $RPrice, true, null, null, null, $this->StoreId);
      }
      $SPrice = $ProdSPrice;
      if ($SPrice >= 0 && $this->includetaxes) {
        $SPrice = $this->taxhelper->getPrice($product, $SPrice, true, null, null, null, $this->StoreId);
      }
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("resulting prices: Regular=" . $RPrice . " Special=" . $SPrice . " FromDate=" . $ProdSFromDate . " ToDate=" . $ProdSToDate . "
");
      }
      $ItemAttributes = "";
      if ($this->includeinvqty) {
        $ItemAttributes = "Inventory=" . $this->_formatQty("" . $this->catalogInventoryModel->loadByProduct($product)->getQty());
      }
      $RPrice = $this->_formatPrice($RPrice);
      if ($this->includespecialprice) {
        if ($SPrice >= 0) {
          $SPrice = $this->_formatPrice($SPrice);
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price=" . $SPrice;
        }
      }
      if ($this->includespecialpricedatefrom) {
        if (!empty($ProdSFromDate)) {
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price From Date=" . substr($ProdSFromDate, 0, 10);
        }
      }
      if ($this->includespecialpricedateto) {
        if (!empty($ProdSToDate)) {
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "Special Price To Date=" . substr($ProdSToDate, 0, 10);
        }
      }
      if ($this->getgroupprices && $ProdSpecGroupPrices) {
        foreach ($ProdSpecGroupPrices as $groupPrice) {
          $price = 0.0 + $groupPrice['price'];
          if ($price >= 0 && $this->includetaxes) {
            $price = $this->taxhelper->getPrice($product, $price, true, null, null, null, $this->StoreId);
          }
          if (!empty($ItemAttributes)) {
            $ItemAttributes .= "|";
          }
          $ItemAttributes .= "price_" . $this->customerGroups[$groupPrice['cust_group']] . "=" . $this->_formatPrice($price);
        }
      }
      $ProducttoString = $prodSku //ItemID
        . "\t" //ItemQty
        . "\t" //ItemUom
        . "\t" . $RPrice //ItemPrice
        . "\t" //ItemDescription
        . "\t" //ItemLink
        . "\t" . $ItemAttributes //ItemAttributes
        . "\t" //ItemitemGraphic
        . "\t" . $prodName //ProductName
        . "\t" . $ProductDescription //ProductDescription
        . "\t" . $prodImages //ProductGraphic
        . "\t" . $URL //ProductLink
        . "\t" . $ProductAttributes //ProductAttributes
        . "\t" . $Manufacturer //Manufacturer
        . "\t" //Category
        . "\t" . $Reviews //Reviews
        . "\t" . $shortDescription
        . "\t1"
        . "\n";
    }
    return $ProducttoString;
  }

  private $prodLine = null;
  private $configItemSequence = 0;
  private $configProducttoString = "";

  private function _fillCPLinesMatrix($attrNumber, $optNo, &$cpLine, &$cpLinesMatrix) {
    $optionId = $cpLine->optionIds[$attrNumber];
    if ($attrNumber < $optNo - 1) {
      if (!isset($cpLinesMatrix[$optionId])) {
        $cpLinesMatrix[$optionId] = array();
      }
      $this->_fillCPLinesMatrix($attrNumber + 1, $optNo, $cpLine, $cpLinesMatrix[$optionId]);
    } else {
      if (!isset($cpLinesMatrix[$optionId])) {
        $cpLinesMatrix[$optionId] = $cpLine;
      }
    }
  }

  private function _fillConfigProdItems($attrNumber, $attrNo, &$optNumbers, &$optionIds, &$optCounters, &$cpLinesMatrix) {
    $optNumber = 0;
    $optionIdNo = $optCounters[$attrNumber];
    while ($optNumber < $optionIdNo) {
      $optionID = $optionIds[$attrNumber][$optNumber];
      if (isset($cpLinesMatrix[$optionID])) {
        if ($attrNumber < $attrNo - 1) {
          $optNumbers[$attrNumber] = $optNumber;
          $this->_fillConfigProdItems($attrNumber + 1, $attrNo, $optNumbers, $optionIds, $optCounters, $cpLinesMatrix[$optionID]);
        } else {
          $cpLine = $cpLinesMatrix[$optionID];
          $this->configProducttoString .= $cpLine->line
            . $this->prodLine
            . $this->configItemSequence
            . "\n";
          $cpLine->line = FALSE;
          $this->configItemSequence++;
        }
      }
      $optNumber++;
    }
  }

  private function _getCustomOptionsAsAttributes(&$product) {
    $attributes = "";
    $customOptions = $product->getOptions();
    if ($customOptions) {
//We should support all 4 selects: dropdown, radio buttons, checkbox, and multiple select. 
//\app\code\core\Mage\Adminhtml\Block\Catalog\Product\Edit\Tab\Options\Option.php 
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("=== customOptions ===
");
      }
      $max_sort_order = 0;
      foreach ($customOptions as $option) {
        if ($option->getSortOrder() > $max_sort_order) {
          $max_sort_order = $option->getSortOrder();
        } else if ($option->getSortOrder() < 0) {
          $option->setSortOrder(-1);
        }
      }
      if ($this->_DEBUG) {
        for ($sortorder = -1; $sortorder <= $max_sort_order; $sortorder++) {
          foreach ($customOptions as $option) {
            if ($option->getSortOrder() != $sortorder) {
              continue;
            }
            $this->controller->getResponse()->appendBody("customOption #" . $option->getOptionId() . " title=" . $option->getTitle() . " storeTitle=" . $option->getStoreTitle() . " type=" . $option->getType() . " sort_order" . $option->getSortOrder() . "
");
            if ($option->getValues()) {
              $max_v_sort_order = 0;
              foreach ($option->getValues() as $_value) {
                if ($_value->getSortOrder() > $max_v_sort_order) {
                  $max_v_sort_order = $_value->getSortOrder();
                } else if ($_value->getSortOrder() < 0) {
                  $_value->setSortOrder(-1);
                }
              }
              for ($vsortorder = -1; $vsortorder <= $max_v_sort_order; $vsortorder++) {
                if ($_value->getSortOrder() != $vsortorder) {
                  continue;
                }
                foreach ($option->getValues() as $_value) {
                  $this->controller->getResponse()->appendBody("value title=" . $_value->getTitle() . " storeTitle=" . $option->getStoreTitle() . " price=" . $_value->getPrice() . " price_type=" . $_value->getPriceType() . " sku=" . $_value->getSku() . " sort_order=" . $_value->getSortOrder() . "
");
                }
              }
            }
          }
        }
      }
      for ($sortorder = -1; $sortorder <= $max_sort_order; $sortorder++) {
        foreach ($customOptions as $option) {
          if ($option->getSortOrder() != $sortorder) {
            continue;
          }
          if ($option->getType() != "drop_down" && $option->getType() != "radio" && $option->getType() != "multiple" && $option->getType() != "checkbox") {
            continue;
          }
          $optionTitle = $option->getStoreTitle();
          if (empty($optionTitle)) {
            $optionTitle = $option->getTitle();
          }
          $optionValues = "";
          if ($option->getValues()) {
            $max_v_sort_order = 0;
            foreach ($option->getValues() as $_value) {
              if ($_value->getSortOrder() > $max_v_sort_order) {
                $max_v_sort_order = $_value->getSortOrder();
              } else if ($_value->getSortOrder() < 0) {
                $_value->setSortOrder(-1);
              }
            }
            for ($vsortorder = -1; $vsortorder <= $max_v_sort_order; $vsortorder++) {
              foreach ($option->getValues() as $_value) {
                if ($_value->getSortOrder() != $vsortorder) {
                  continue;
                }
                if (!empty($optionValues)) {
                  $optionValues .= "<br/>";
                }
                $valueTitle = $_value->getStoreTitle();
                if (empty($valueTitle)) {
                  $valueTitle = $_value->getTitle();
                }
                $optionValues .= $valueTitle;
              }
            }
          }
          if (!empty($attributes)) {
            $attributes .= "|";
          }
          $attributes .= $optionTitle . "=" . $optionValues;
        }
      }
    }
    return $attributes;
  }

  private function _buildCOValueSets(&$coValueSetSequences, $coValueSetSequence, $index, &$customOptions) {
    $cOption = $customOptions[$index];
    $customOptionCount = count($customOptions);
    $coValueSetList = array();
    if ($cOption->type == "multiple" || $cOption->type == "checkbox") {
      // all combinations + empty if not required
      $valuesCount = count($cOption->values);
      $indexList = array();
      $indices = array();
      $this->_buildCOValueIndices($indexList, $indices, $valuesCount - 1);
      foreach ($indexList as $indices) {
        $coValueSet = new COValueSet();
        $coValueSet->optionId = $cOption->id;
        $valueIndexNo = 0;
        foreach ($indices as $valueIndex) {
          if ($valueIndex == 1) {
            $coValueSet->coValues[] = $cOption->values[$valueIndexNo];
          }
          $valueIndexNo++;
        }
        if (!$cOption->required && count($coValueSet->coValues) == 0) {
          $coValue = new COValue();
          $coValue->title = FALSE;
          $coValue->sku = FALSE;
          $coValue->rprice = 0;
          $coValue->sprice = 0;
          $coValueSet->coValues[] = $coValue;
        }
        if (count($coValueSet->coValues) == 0) {
          continue;
        }
        if (count($coValueSet->coValues) > 1) {
          $coValueSet->coValues = array_reverse($coValueSet->coValues);
        }
        $coValueSetList[] = $coValueSet;
      }
    } else {
      foreach ($cOption->values as $optionValue) {
        $coValueSet = new COValueSet();
        $coValueSet->optionId = $cOption->id;
        $coValueSet->coValues[] = $optionValue;
        $coValueSetList[] = $coValueSet;
      }
    }
    $coValueSetListCount = count($coValueSetList);
    foreach ($coValueSetList as $coValueSet) {
      $coValueSetSequence[$index] = $coValueSet;
      if ($index == $customOptionCount - 1)
        $coValueSetSequences[] = $coValueSetSequence;
      else
        $this->_buildCOValueSets($coValueSetSequences, $coValueSetSequence, $index + 1, $customOptions);
    }
  }

  private function _buildCOValueIndices(&$indexList, $indices, $index) {
    for ($i = 0; $i < 2; $i++) {
      $indices[$index] = $i;
      if ($index == 0) {
        $indexList[] = $indices;
      } else {
        $this->_buildCOValueIndices($indexList, $indices, $index - 1);
      }
    }
  }

  private function _cleanStr(&$str) {
    return str_replace("\n", " ", str_replace("\r", " ", str_replace("\r\n", " ", str_replace("\t", "    ", $str))));
  }

  private function _correctProdUrlStr(&$str) {
    $str = str_replace(self::SCRIPTNAME, "index.php", $str);
    $pos = strpos($str, "?");
    if ($pos === false) {
      return $str;
    } else if (substr($str, $pos) == "?___store=default") {
      return substr($str, 0, $pos);
    } else {
      return $str;
    }
  }

  private function _getReviews($productid) {
    $reviewsCollection = $this->reviewsModel->getCollection()
      ->addStoreFilter($this->StoreId)
      ->addStatusFilter('approved')
      ->addEntityFilter('product', $productid)
      ->setDateOrder();
    $Reviews = "";
    foreach ($reviewsCollection as $review) {
      $Reviews .= "<p><b>" . $review->getTitle() . "(" . $review->getNickname() . ")</b><br>" . $review->getDetail() . self::TAG_P_CLOSE;
    }
    unset($reviewsCollection);
    return $Reviews;
  }

  private function _formatPrice($Price) {
    $v = 0.0;
    if (is_string($Price)) {
      $rv = str_replace("\n", " ", str_replace("\r", " ", str_replace("\r\n", " ", str_replace("\t", "    ", $Price))));
      if (empty($rv) || $rv == "0") {
        return "";
      }
      $v = floatval($Price);
    } else if (is_float($Price)) {
      $v = $Price;
    } else {
      $v = floatval($Price);
    }
    if ($v <= 0) {
      return "";
    }
    $v100 = $v * 100;
    $iv100 = intval($v100);
    if ($v100 - $iv100 >= 0.5) {
      $iv100 = $iv100 + 1;
    }
    $rv = strval(floatval($iv100) / 100);
    $tail = strrchr($rv, ".");
    if (!empty($tail) && strlen($tail) > 3) {
      $rv = substr($rv, 0, strlen($rv) - ( strlen($tail) - 3 ));
    } else if (!empty($tail) && strlen($tail) == 2) {
      $rv = $rv . "0";
    } else if (!empty($tail) && strlen($tail) == 1) {
      $rv = $rv . "00";
    } else if (!$tail) {
      $rv = $rv . ".00";
    }
    return $rv;
  }

  private function _formatQty($qty) {
    $tail = strrchr($qty, ".");
    if (!empty($tail)) {
      $qty = substr($qty, 0, strlen($qty) - strlen($tail));
    }
    return $qty;
  }

  private function _formatImageURL($str) {
    if ($str[0] != '/') {
      $str = "/" . $str;
    }
    return $str;
  }

  private function _getTierPrices(&$product) {
    $res = "";
    $prices = $product->getFormatedTierPrice();
    if ($this->_DEBUG && isset($prices)) {
      $this->controller->getResponse()->appendBody("public tier prices: " . var_export($prices, true) . "
");
    }
    $rightstr = "";
    if (is_array($prices)) {
      $count = count($prices);
      if ($count > 0) {
        $prodFinalPrice = $this->includetaxes ?
          $this->taxhelper->getPrice($product, $product->getData('price'), true, null, null, null, $this->StoreId) :
          $product->getData('price');
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("prodFinalPrice: " . $prodFinalPrice . "
");
        }
        $res = '[TierPriceTable]#$$#' . $this->quantitylabel . '#$#' . $this->pricelabel . '#$#' . $this->savingslabel . "=";
        $i = 1;
        foreach ($prices as $price) {
          $price['price_qty'] = $price['price_qty'] * 1;
          $tierPrice = $this->includetaxes ?
            $this->taxhelper->getPrice($product, $price['price'], true, null, null, null, $this->StoreId) :
            $price['price'];
          if ($tierPrice < $prodFinalPrice) {
            $tierPrice = $this->_formatPrice("" . $tierPrice);
            $rightstr .= $price['price_qty'] . '#$#';
            $rightstr.=$tierPrice . '#$#';
            if ($i == $count) {
              $rightstr .= ceil(100 - (( 100 / $prodFinalPrice ) * $tierPrice )) . '%';
            } else {
              $rightstr .= ceil(100 - (( 100 / $prodFinalPrice ) * $tierPrice )) . '%#$$#';
            }
          }
          $i++;
        }
      }
      if (empty($rightstr)) {
        $res = "";
      }
    }
    unset($prices);
    if ($this->getgroupprices) {
      $pairs = array();
      if (!empty($res)) {
        $pairs[] = $res . $rightstr;
      }
      $prices = $product->getData('tier_price');
      if ($this->_DEBUG && isset($prices)) {
        $this->controller->getResponse()->appendBody("all tier prices: " . var_export($prices, true) . "
");
      }
      foreach ($this->customerGroups as $gid => $gname) {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("group: " . $gid . "=>" . $gname . "
");
        }
        if ($gid == 0) {
          continue;
        }
        $res = "";
        $rightstr = "";
        $gprices = array();
        foreach ($prices as $price) {
          if ($price['all_groups'] == '1' || $price['cust_group'] == $gid) {
            $gprices[] = $price;
          }
        }
        $count = count($gprices);
        if ($count > 0) {
          if (!isset($prodFinalPrice)) {
            $prodFinalPrice = $this->includetaxes ?
              $this->taxhelper->getPrice($product, $product->getData('price'), true, null, null, null, $this->StoreId) :
              $product->getData('price');
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody("prodFinalPrice: " . $prodFinalPrice . "
");
            }
          }
          $res = '[TierPriceTable_' . $gname . ']#$$#' . $this->quantitylabel . '#$#' . $this->pricelabel . '#$#' . $this->savingslabel . "=";
          $i = 1;
          foreach ($gprices as $price) {
            $price['price_qty'] = $price['price_qty'] * 1;
            $tierPrice = $this->includetaxes ?
              $this->taxhelper->getPrice($product, $price['price'], true, null, null, null, $this->StoreId) :
              $price['price'];
            if ($tierPrice < $prodFinalPrice) {
              $tierPrice = $this->_formatPrice("" . $tierPrice);
              $rightstr .= $price['price_qty'] . '#$#';
              $rightstr.=$tierPrice . '#$#';
              if ($i == $count) {
                $rightstr .= ceil(100 - (( 100 / $prodFinalPrice ) * $tierPrice )) . '%';
              } else {
                $rightstr .= ceil(100 - (( 100 / $prodFinalPrice ) * $tierPrice )) . '%#$$#';
              }
            }
            $i++;
          }
        }
        if (!empty($rightstr)) {
          $pairs[] = $res . $rightstr;
        }
      }
      return count($pairs) > 0 ? implode("|", $pairs) : FALSE;
    } else
      return $res . $rightstr;
  }

  // Class=CatalogSection
  public function renderCatalogSection() {
    $categories = Mage::getModel('catalog/category')
      ->setStoreId($this->StoreId)
      ->getCollection()
      ->addAttributeToSelect('name')
      ->addAttributeToFilter('level', array('eq' => 2))
      ->addAttributeToFilter('is_active', array('eq' => 1))
      ->addAttributeToSort('position', 'asc');
    $i = 1;
    $count = count($categories);
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("StoreId: " . $this->StoreId . " Top categories: " . $count . "
");
    }
    $this->controller->getResponse()->appendBody("sec_Project\tsec_Sequence\tsec_HierarchyPath\tsec_Flag\n");
    if ($count > 0) {
      if ($this->ignoretopcategory) {
        foreach ($categories as $topcategory) {
          $children = $topcategory->getCollection();
          $children->setStoreId($this->StoreId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', array('eq' => 1))
            ->addAttributeToSort('position', 'asc')
            ->addIdFilter($topcategory->getChildren())
            ->load();
          foreach ($children as $category) {
            $name = $category->getName();
            $id = $category->getId();
            $this->controller->getResponse()->appendBody($this->_drawCategory($category, 1, $name, $i));
            $i++;
          }
        }
      } else {
        foreach ($categories as $category) {
          $name = $category->getName();
          $id = $category->getId();
          $this->controller->getResponse()->appendBody($this->_drawCategory($category, 1, $name, $i));
          $i++;
        }
      }
    }
    $lastId = $i;
    $this->controller->getResponse()->appendBody("General\t" . $lastId . "\tUncategorized\t
");
    $this->controller->getResponse()->appendBody("==EOF==");
  }

  private function _drawCategory(&$category, $level = 0, $hpath = '', $i = 1, $j = 1, $path = '') {
    $html = "";
    $children = $category->getCollection();
    $children->setStoreId($this->StoreId)
      ->addAttributeToSelect('name')
      ->addAttributeToFilter('is_active', array('eq' => 1))
      ->addAttributeToSort('position', 'asc')
      ->addIdFilter($category->getChildren())
      ->load();
    $sec_path = "";
    if ($level != 1) {
      $path .= '-' . $j;
      $hpath = $hpath . '#$#' . $category->getName(); //.' '.$i.$path;
      $sec_path .= str_replace("-", ",", $path);
      $seq = $i . $sec_path;
    } else {
      $hpath = $category->getName(); //.' '.$i;
      $seq = $i;
    }
    $html = "General\t" . $seq . "\t" . $hpath . "\t\n";
    $htmlChildren = "";
    if (count($children)) {
      $j = 1;
      foreach ($children as $cat) {
        $htmlChildren .= $this->_drawCategory($cat, $level + 1, $hpath, $i, $j, $path);
        $j++;
      }
    }
    unset($children);
    return $html .= $htmlChildren;
  }

  // Class=CatalogProject
  public function renderCatalogProject() {
    if ($this->splitgroupedproducts) {
      $this->productTypeGroupedModel = Mage::getModel('catalog/product_type_grouped');
    }
    $this->productTypeConfigurableModel = Mage::getModel('catalog/product_type_configurable');
    $this->storeSorting = Mage::getSingleton('catalog/config')->getProductListDefaultSortBy($this->StoreId);
    $categories = Mage::getModel('catalog/category')
      ->setStoreId($this->StoreId)
      ->getCollection()
      ->addAttributeToSelect('name')
      ->addAttributeToSelect('default_sort_by')
      ->addAttributeToFilter('level', array('eq' => 2))
      ->addAttributeToFilter('is_active', array('eq' => 1))
      ->addAttributeToSort('position', 'asc');
    $count = count($categories);
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("StoreId: " . $this->StoreId . " Top categories: " . $count . "
");
    }
    if ($count > 0) {
      $hpath = "";
      if ($this->ignoretopcategory) {
        foreach ($categories as $topcategory) {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("Category: " . $topcategory->getName() . "
");
            $this->controller->getResponse()->appendBody("DefaultSortBy: " . $topcategory->getDefaultSortBy() . "
");
          }
          $children = $topcategory->getCollection();
          $children->setStoreId($this->StoreId)
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('default_sort_by')
            ->addAttributeToFilter('is_active', array('eq' => 1))
            ->addAttributeToSort('position', 'asc')
            ->addIdFilter($topcategory->getChildren())
            ->load();
          foreach ($children as $category) {
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody("Category: " . $category->getName()
                . " sortBy: " . $category->_getData('default_sort_by')
                . " storeSortBy: " . Mage::getSingleton('catalog/config')->getProductListDefaultSortBy($this->StoreId)
                . " resulting DefaultSortBy: " . $category->getDefaultSortBy()
                . "
");
            }
            $this->catmap[$category->getId()] = $category;
            $this->_processCategory($category, 1, $hpath);
          }
        }
      } else {
        foreach ($categories as $category) {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("Category: " . $category->getName()
              . " sortBy: " . $category->_getData('default_sort_by')
              . " storeSortBy: " . Mage::getSingleton('catalog/config')->getProductListDefaultSortBy($this->StoreId)
              . " resulting DefaultSortBy: " . $category->getDefaultSortBy()
              . "
");
          }
          $this->catmap[$category->getId()] = $category;
          $this->_processCategory($category, 1, $hpath);
        }
      }
    }
    $this->unCatPosition = 1;
    if ($this->start == 0) {
      $this->controller->getResponse()->appendBody("proj_Key\tproj_ProdName\tproj_Sequence\tproj_Name\tproj_HierarchyPath\tproj_Flag\tproj_ProdLayout
");
    }
    $products = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->getCollection();
    $products->addAttributeToFilter('status', 1); //enabled
    $products->addAttributeToFilter('visibility', 4); //catalog, search
    $prodIds = $this->_DEBUG ? $products->getAllIds(1000, 0) : $products->getAllIds();
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("StoreId: " . $this->StoreId . " Products: " . count($prodIds) . "
");
    }
    $count = 0;
    $index = 0;
    $lastLine = "==EOF==
";
    foreach ($prodIds as $productId) {
      if ($count == $this->pageSize) {
        $lastLine = "==MORE==
";
        break;
      }
      if ($index < $this->start) {
        $index++;
        continue;
      }
      $index++;
      $product = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->load($productId);
      if ($product->isConfigurable()) {
        $this->controller->getResponse()->appendBody($this->CatalogProjectStringConfigurable($product));
      } else if ($product->isGrouped() && $this->splitgroupedproducts) {
        $this->controller->getResponse()->appendBody($this->CatalogProjectStringGroupedAsSimple($product));
      } else {
        $this->controller->getResponse()->appendBody($this->CatalogProjectStringGroupedOrSimple($product));
      }
      unset($product);
      if ($this->_DEBUG) {
        $this->controller->getResponse()->appendBody("#" . $count . ": memory_get_usage " . memory_get_usage() . " / " . memory_get_usage(TRUE) . "
");
      }
      $count++;
    }
    $this->controller->getResponse()->appendBody($lastLine);
  }

  private function _processCategory(&$category, $level = 0, $hpath = '', $path = '') {
    $hpath = $level != 1 ? $hpath . '#$#' . $category->getName() : $hpath = $category->getName();
    $this->catpathmap[$category->getId()] = $hpath;
    if ($this->_DEBUG) {
      $this->controller->getResponse()->appendBody("Category: " . $category->getId() . " path=" . $hpath . "
");
    }
    $children = $category->getCollection();
    $children->setStoreId($this->StoreId)
      ->addAttributeToSelect('name')
      ->addAttributeToSelect('default_sort_by')
      ->addAttributeToFilter('is_active', array('eq' => 1))
      ->addAttributeToSort('position', 'asc')
      ->addIdFilter($category->getChildren())
      ->load();
    $htmlChildren = "";
    if (count($children)) {
      foreach ($children as $childcategory) {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("Category: " . $childcategory->getName()
            . " sortBy: " . $childcategory->_getData('default_sort_by')
            . " storeSortBy: " . Mage::getSingleton('catalog/config')->getProductListDefaultSortBy($this->StoreId)
            . " resulting DefaultSortBy: " . $childcategory->getDefaultSortBy()
            . "
");
        }
        $this->catmap[$childcategory->getId()] = $childcategory;
        $htmlChildren .= $this->_processCategory($childcategory, $level + 1, $hpath, $path);
      }
    }
  }

  private function CatalogProjectStringGroupedOrSimple(&$product) {
    $ProducttoString = "";
    $categoryIds = $product->getCategoryIds();
    $listed = FALSE;
    if ($categoryIds) {
      foreach ($categoryIds as $k => $category_id) {
        if (!isset($this->catmap[$category_id])) {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("No category: " . $category_id . "
");
          }
          continue;
        }
        $category = $this->catmap[$category_id];
        $hpath = $this->catpathmap[$category_id];
        $position = "";
        $positions = $category->getProductsPosition();
        if (count($positions) > 0) {
          $position = $positions[$product->getId()];
        }
        $productName = $this->_cleanStr($product->getName());
        $categorySorting = $category->getDefaultSortBy();
        $productSortValue = $this->CatalogProjectSortValue($product, $productName, $categorySorting);
        $ProducttoString .= $product->getId() . '#$#' . $productName //ProductKey
          . "\t" . $product->getId() . '#$#' . $productName  //Product Name                        
          . "\t" . $position //Prod Sequence
          . "\tGeneral" //Proj Name  
          . "\t" . $hpath //Hirarachi Path
          . "\t" . $categorySorting //Proj Flag
          . "\t" . $productSortValue//Proj Prod layout
          . "\n";
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("Product #" . $product->getId() . ": position=" . $position . " price=" . $product->getData('price') . "
");
        }
        $listed = TRUE;
      }
    }
    if (!$listed) {
      $productName = $this->_cleanStr($product->getName());
      $productSortValue = $this->CatalogProjectSortValue($product, $productName, $this->storeSorting);
      $ProducttoString .= $product->getId() . '#$#' . $productName //ProductKey
        . "\t" . $product->getId() . '#$#' . $productName //Product Name                        
        . "\t" . $this->unCatPosition //Prod Sequence
        . "\tGeneral" //Proj Name  
        . "\tUncategorized" //Hirarachi Path
        . "\t" . $this->storeSorting //Proj Flag
        . "\t" . $productSortValue//Proj Prod layout
        . "\n";
      $this->unCatPosition++;
    }
    return $ProducttoString;
  }

  private function CatalogProjectStringGroupedAsSimple(&$product) {
    $AssociatedProductIds = $this->productTypeGroupedModel->getAssociatedProductIds($product);
    $AssociatedProducts = array();
    foreach ($AssociatedProductIds as $UsedProductid) {
      $UsedProduct = Mage::getModel('catalog/product')->setStoreId($this->StoreId)->load($UsedProductid);
      if ($UsedProduct->getStatus() == 1) {
        $AssociatedProducts[] = $UsedProduct;
      } else {
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("sku=" . $UsedProduct->getSku() . " enabled=" . $UsedProduct->getStatus() . " - bypassed.
");
        }
        unset($UsedProduct);
      }
    }
    $ProducttoString = "";
    if (count($AssociatedProducts) == 0) {
      return $ProducttoString;
    }
    $listed = FALSE;
    $categoryIds = $product->getCategoryIds();
    if ($categoryIds) {
      foreach ($categoryIds as $k => $category_id) {
        if (!isset($this->catmap[$category_id])) {
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("No category: " . $category_id . "
");
          }
          continue;
        }
        $category = $this->catmap[$category_id];
        $hpath = $this->catpathmap[$category_id];
        $position = "";
        $positions = $category->getProductsPosition();
        if (count($positions) > 0) {
          $position = $positions[$product->getId()];
        }
        $categorySorting = $category->getDefaultSortBy();
        foreach ($AssociatedProducts as $UsedProduct) {
          $productName = $this->_cleanStr($UsedProduct->getName());
          $productSortValue = $this->CatalogProjectSortValue($UsedProduct, $productName, $categorySorting);
          $ProducttoString .= $UsedProduct->getId() . '#$#' . $productName //ProductKey
            . "\t" . $UsedProduct->getId() . '#$#' . $productName  //Product Name                        
            . "\t" . $position //Prod Sequence
            . "\tGeneral" //Proj Name  
            . "\t" . $hpath //Hirarachi Path
            . "\t" . $categorySorting //Proj Flag
            . "\t" . $productSortValue//Proj Prod layout
            . "\n";
          $position += 0.001;
        }
        $listed = TRUE;
      }
    }
    if (!$listed) {
      foreach ($AssociatedProducts as $UsedProduct) {
        $productName = $this->_cleanStr($UsedProduct->getName());
        $productSortValue = $this->CatalogProjectSortValue($UsedProduct, $productName, $this->storeSorting);
        $ProducttoString .= $UsedProduct->getId() . '#$#' . $productName //ProductKey
          . "\t" . $UsedProduct->getId() . '#$#' . $productName  //Product Name                        
          . "\t" . $this->unCatPosition //Prod Sequence
          . "\tGeneral" //Proj Name  
          . "\tUncategorized" //Hirarachi Path
          . "\t" . $this->storeSorting //Proj Flag
          . "\t" . $productSortValue //Proj Prod layout
          . "\n";
        $this->unCatPosition++;
      }
    }
    foreach ($AssociatedProducts as $UsedProduct) {
      unset($UsedProduct);
    }
    return $ProducttoString;
  }

  private function CatalogProjectStringConfigurable(&$product) {
    $UsedProductIds = $this->productTypeConfigurableModel->getUsedProductIds($product);
    $countUsedProductIds = count($UsedProductIds);
    if ($countUsedProductIds > 0) {
      $listed = FALSE;
      $ProducttoString = "";
      $categoryIds = $product->getCategoryIds();
      if ($categoryIds) {
        foreach ($categoryIds as $k => $category_id) {
          if (!isset($this->catmap[$category_id])) {
            if ($this->_DEBUG) {
              $this->controller->getResponse()->appendBody("No category: " . $category_id . "
");
            }
            continue;
          }
          $category = $this->catmap[$category_id];
          $hpath = $this->catpathmap[$category_id];
          $position = "";
          $positions = $category->getProductsPosition();
          if (count($positions) > 0) {
            $position = $positions[$product->getId()];
          }
          $productName = $this->_cleanStr($product->getName());
          $categorySorting = $category->getDefaultSortBy();
          $productSortValue = $this->CatalogProjectSortValue($product, $productName, $categorySorting);
          $ProducttoString .= $product->getId() . '#$#' . $productName //ProductKey
            . "\t" . $product->getId() . '#$#' . $productName  //Product Name
            . "\t" . $position //Prod Sequence
            . "\tGeneral" //Proj Name  
            . "\t" . $hpath //Hirarachi Path
            . "\t" . $categorySorting //Proj Flag
            . "\t" . $productSortValue//Proj Prod layout
            . "\n";
          $listed = TRUE;
        }
      }
      if (!$listed) {
        $productName = $this->_cleanStr($product->getName());
        $productSortValue = $this->CatalogProjectSortValue($product, $productName, $this->storeSorting);
        $ProducttoString .= $product->getId() . '#$#' . $productName //ProductKey
          . "\t" . $product->getId() . '#$#' . $productName //Product Name                        
          . "\t" . $this->unCatPosition //Prod Sequence
          . "\tGeneral" //Proj Name  
          . "\tUncategorized" //Hirarachi Path
          . "\t" . $this->storeSorting //Proj Flag
          . "\t" . $productSortValue//Proj Prod layout
          . "\n";
        $this->unCatPosition++;
      }
    } else {
      $ProducttoString = "";
    }
    unset($UsedProductIds);
    return $ProducttoString;
  }

  private function CatalogProjectSortValue(&$product, &$productName, &$categorySorting) {
    $productSortValue = false;
    if ("position" == $categorySorting) {
      $productSortValue = strtolower(trim($productName));
    } else if ("name" == $categorySorting) {
      $productSortValue = strtolower(trim($productName));
    } else if ("price" == $categorySorting) {
      $ProdRPrice = $product->getData('price');
      if (!$ProdRPrice && $product->getTypeId() == "bundle") {
        $priceModel = $product->getPriceModel();
        $options = Mage::getSingleton('core/layout')->createBlock('bundle/catalog_product_view_type_bundle')->setProduct($product)->getOptions();
        $ProdRPrice = 0;
        foreach ($options as $option) {
          $selection = $option->getDefaultSelection();
          $MinSelPrice = 0;
          if ($selection) {
            $MinSelPrice = $priceModel->getSelectionPreFinalPrice($product, $selection, $selection->getSelectionQty());
          } else if (!$selection && $option->_origData["required"] == "1") {
            foreach ($option->getSelections() as $selection) {
              $SelPrice = $priceModel->getSelectionPreFinalPrice($product, $selection, $selection->getSelectionQty());
              if (( $MinSelPrice == 0 && $SelPrice > 0 ) || ( $MinSelPrice > 0 && $SelPrice > 0 && $SelPrice < $MinSelPrice )) {
                $MinSelPrice = $SelPrice;
              }
            }
          }
          if ($MinSelPrice <= 0) {
            continue;
          }
          if ($this->_DEBUG) {
            $this->controller->getResponse()->appendBody("added " . $MinSelPrice . "
");
          }
          $ProdRPrice += $MinSelPrice;
        }
        if ($this->_DEBUG) {
          $this->controller->getResponse()->appendBody("Bundle=" . $ProdRPrice . "
");
        }
      }
      if (!$ProdRPrice) {
        $ProdRPrice = "0.0";
      }
      $productSortValue = $ProdRPrice;
    }
    return $productSortValue;
  }

}

// Mage_CodiScript_Model_Files