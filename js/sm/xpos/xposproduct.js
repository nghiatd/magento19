(function (jQuery) {
    jQuery(document).ready(function () {
        productData = prepareProductData();
        var orders = $.jStorage.get("orders");
        if (orders!=null){
            jQuery('#count_pending_orders').html(orders.length);
        }

        //search base jquery autocomplete
        jQuery('#search-box').keyup( function (event) {
//            if ( event.which != 13 ) {
//                return;
//            }
            if(event.ctrlKey) return;
            var term = jQuery('#search-box').val();
            //alert(term);

            jQuery("#product-info").empty();
            //jQuery('#search-box').val('');
            if (offlineSearch){
                //var data = search(term, productData);
                term = term.toLowerCase();
                var result = [];
                var number_result = jQuery("#result_number_search").val();
                jQuery.each(productData, function (i, item) {
                    if (result.length < number_result &&
                        (item['name'].toLowerCase().match(term) || item['sku'].toLowerCase().match(term) ||
                            (item['searchString'] != null && item['searchString'].toLowerCase().match(term)))) {
                        result.push(item);
                    }
                })
                if (result.length == 1) {
                    if(event.which ==13){
                        addToCart(result[0].id);
                        document.getElementById("search-box").select();
                    }
                }
                var data= result;

                if (data.length == 0) {
                    jQuery('#category-selected').text("No search result for : " + term);
                    jQuery('#category-selected').append('<i>icon</i>');
                    return;
                }
                var count_rs=0;
                jQuery.each(data, function (i, item) {
                    displayProductItem(item, true);
                    count_rs++;
                });
                var result_string  = "result";
                if(count_rs > 1){
                    result_string  = "results";
                }
                jQuery('#category-selected').text(count_rs+ ' ' + result_string + ' for: ' + term);
                jQuery('#category-selected').append('<i>icon</i>');
                initScroll("#product-info");
            }else{
                if(isOnline() ){
                    jQuery('#category-selected').text("Searching online result for : " + term);
                    jQuery('#category-selected').append('<i>icon</i>');

                    var url = searchProductUrl+"?query="+term;
                    jQuery.getJSON(url, function (json) {
                        var p = json;
                        var data = [];
                        jQuery("#product-info").empty();
                        for (var i = 0;i< p.length;i++){
                            data.push(productData[p[i]]);
                        }

                        if (data.length == 0) {
                            jQuery('#category-selected').text("No search result for : " + term);
                            jQuery('#category-selected').append('<i>icon</i>');
                            return;
                        }

                        var count_rs=0;
                        jQuery.each(data, function (i, item) {
                            displayProductItem(item, true);
                            count_rs++;
                        });

                        var result_string  = "result";
                        if(count_rs > 1){
                            result_string  = "results";
                        }
                        jQuery('#category-selected').text(count_rs+ ' ' + result_string + ' for: ' + term);
                        jQuery('#category-selected').append('<i>icon</i>');
                        initScroll("#product-info");

                        if (data.length == 1) {
                            if ( event.which != 13 ) {
                                return;
                            }
                            else{
                                addToCart(data[0].id);
                                document.getElementById("search-box").select();
                                //window.product_cart_id = data[0].id;
                            }

                        }
                    })
                }
            }
        });

        jQuery('body').on("keydown",'.item-price',function(event){

            if (event.shiftKey == true) {
                event.preventDefault();
            }

            if ((event.keyCode >= 48 && event.keyCode <= 57) ||
                (event.keyCode >= 96 && event.keyCode <= 105) ||
                event.keyCode == 8 || event.keyCode == 9 || event.keyCode == 37 ||
                event.keyCode == 39 || event.keyCode == 46 || event.keyCode == 190 || event.keyCode == 110) {

            } else {
                event.preventDefault();
            }

            if(jQuery(this).val().indexOf('.') !== -1 && event.keyCode == 190)
                event.preventDefault();

            if(jQuery(this).val().indexOf('.') !== -1 && event.keyCode == 110)
                event.preventDefault();
        });

    });
})(jQuery);

var data;
var page = 1;
var totalLoaded = 0;
function showCategoryDefault(){
    productData = prepareProductData();
    //show product default category in xpos, should be place after prepare productdata
    var default_cate = jQuery("#cate_default").val();
    var cate_default_name = jQuery("#cate_default_name").val();
    if(default_cate!=undefined){
        displayProduct(default_cate,0);
        initScroll("#product-info");
        // alert(cate_default_name);
        jQuery('#category-selected').text(cate_default_name);
        jQuery('#category-selected').append('<i>icon</i>');
        if (jQuery('#category-selected').hasClass("hide")) {
            jQuery('#category-list').slideDown('slow');
            jQuery('#category-selected').removeClass('hide').addClass('show');
        } else {
            jQuery('#category-list').slideUp('slow');
            jQuery('#category-selected').removeClass('show').addClass('hide');
        }
    }
}
function getData() {
    var warehouseId = $.jStorage.get('xpos_warehouse');
    var data_load_interval = jQuery('#data_load_interval').val() * 1000;
    var append = '';
    if (warehouseId!=null){
        append='?warehouse='+warehouseId.warehouse_id;
    }

    jQuery.getJSON(loadProductUrl+append, function (json) {

        data = json;
        var totalLoad = data['totalLoad'];
        var totalProduct = data['totalProduct'];
        totalLoaded += totalLoad;

        saveData(data, page);
        var status = 'Updated ' + totalLoaded + '/' + totalProduct +' products. Saved:'+Object.keys(productData).length ;
        jQuery('#status').text(status);
        var oldParam = 'page/' + page;
        page++;
        if (totalLoad == 0) {
            page = 1;
            totalLoaded = 0;
        }
        var newParam = 'page/' + page;
        loadProductUrl = loadProductUrl.replace(oldParam, newParam);
        if(window.show_cate_default==null)
            window.show_cate_default = 1;
        if(window.show_cate_default==1){
            showCategoryDefault();
            window.show_cate_default=0;
        }
        setTimeout(function () {
            getData();
        }, data_load_interval);
    });
}

function saveData(data, page) {
    var productInfo = data['productInfo'];
    jQuery.each(productInfo, function (i, item) {
        productData[item.id] = item;
    });
    $.jStorage.set('productData', productData);
}

function prepareProductData() {
    var data = {};
    var storageList = $.jStorage.get('productData');
    if (storageList !=null && storageList != undefined){
        data = storageList;
    }
    return data;
}

function search(term, productData) {
    term = term.toLowerCase();
    var result = [];
    jQuery.each(productData, function (i, item) {
        if (result.length < 51 &&
            (item['name'].toLowerCase().match(term) || item['sku'].toLowerCase().match(term) ||
                (item['searchString'] != null && item['searchString'].toLowerCase().match(term)))) {
            result.push(item);
        }
    })

    if (result.length == 1) {
        addToCart(result[0].id);
    }
    return result;
}

var productTemplate = '<li><div class="product-wrapper"><span class="{type}"></span><a class="product" id="product-{id}" href="#" onclick="addToCart({id})"><img src="{small_image}"><div class="price"><span>{finalPrice}<span></div><span class="name">{name}</span></a></div></li>';
var productSearchTemplate = '<li><div class="product-wrapper"><span class="{type}"></span><a class="product" id="product-{id}" href="#" onclick="addToCart({id})"><img src="{small_image}"><div class="price"><span>{finalPrice}<span></div><span class="name">{sku} - {name}</span></a></div></li>';
function displayProduct(category, page) {
    var productList = getProductByCategory(category,page);
    jQuery("#product-info").attr('data', category);
    jQuery("#product-info").attr('page', page);
    jQuery("#product-info").empty();
    jQuery.each(productList, function (i, item) {
        displayProductItem(item, false);
    });
    initScroll("#product-info");
}

function getProductByCategory(category,page){
    var result = [];
    var p = categoryData[category];
    for (var i = 0;i< p.length;i++){
        result.push(productData[p[i]]);
    }
    return result;
}
//using nano to append product, isSearch = true to show in search mode, false in normal mode
function displayProductItem(item, isSearch) {
    if (item == null || item == undefined || item.sku == null || item.finalPrice == null){
        return;
    }
    var productItem = {};
    productItem.type = item.type;
    productItem.id = item.id;
    productItem.small_image = item.small_image;
    productItem.name = item.name;

    productItem.finalPrice = Number(item.finalPrice).toFixed(2);
    if (displayTaxInCatalog){
        productItem.finalPrice = Number(item.priceInclTax).toFixed(2);
    }
    if (isSearch) {
        jQuery("#product-info").append(nano(productSearchTemplate, productItem).replace('<img src="">', placeholder));
    } else {
        jQuery("#product-info").append(nano(productTemplate, productItem).replace('<img src="">', placeholder));
    }
}


function addToCart(itemId) {
    var item = productData[itemId];
    if (item.options) {
        //show config panel
        optionsPrice = new Product.OptionsPrice({"productId": itemId, "priceFormat": priceFormat, "showBothPrices": true, "productPrice": item.finalPrice, "productOldPrice": item.price, "priceInclTax": item.price, "priceExclTax": item.price, "skipCalculate": 0, "defaultTax": 8.25, "currentTax": 8.25, "idSuffix": "_clone", "oldPlusDisposition": 0, "plusDisposition": 0, "plusDispositionTax": 0, "oldMinusDisposition": 0, "minusDisposition": 0, "tierPrices": [], "tierPricesInclTax": []});
        if (item.type == 'grouped'){
            optionsPrice.productPrice = 0;
            optionsPrice.productOldPrice = 0;
            optionsPrice.priceInclTax = 0;
            optionsPrice.priceExclTax = 0;
        }
        jQuery('#product-option').empty();
        jQuery('#product-option').append('<div class="price-box">Price: <span id="price-excluding-tax-' + itemId + '" class="regular-price">' + item.finalPrice + '</span><span class="including-tax"> Incl. Tax: </span><span id="price-including-tax-' + itemId + '" class="regular-price">' + item.finalPrice + '</span></div>');
        jQuery('#product-option').append(item.options);
        jQuery('#product-option').append('<div class="action"><button id="cancel" class="button cancel" type="button"><span>Cancel</span></button><button id="ok" class="ok" type="submit" ><span>OK</span></button></div>');
        jQuery('#product-option-form').bPopup({closeClass: 'button'});
        var productCompositeConfigureForm = new varienForm('product-option');
        jQuery("#product-option").unbind();
        jQuery("#product-option").submit(function( event ) {
            if (productCompositeConfigureForm.validate()){
                jQuery('#product-option-form').bPopup().close();
                addConfigurableProduct(itemId);
            }
            event.preventDefault();
        });
        optionsPrice.reload();
    } else {
        addOrder(itemId, []);
        displayOrder(currentOrder, true);
    }
    auto_select_field();
    jQuery('#product-info li').removeClass('active');
    jQuery('#product-' + itemId).parent().parent().addClass('active');
    console.log("add to cart " + itemId);
    initScroll("#items_area");
}

//change product bundle
function changeProductBundle(itemId,id_row) {
    var item = productData[itemId];
    if (item.options) {
        //show config panel
        optionsPrice = new Product.OptionsPrice({"productId": itemId, "priceFormat": priceFormat, "showBothPrices": true, "productPrice": item.finalPrice, "productOldPrice": item.price, "priceInclTax": item.price, "priceExclTax": item.price, "skipCalculate": 0, "defaultTax": 8.25, "currentTax": 8.25, "idSuffix": "_clone", "oldPlusDisposition": 0, "plusDisposition": 0, "plusDispositionTax": 0, "oldMinusDisposition": 0, "minusDisposition": 0, "tierPrices": [], "tierPricesInclTax": []});
        if (item.type == 'grouped'){
            optionsPrice.productPrice = 0;
            optionsPrice.productOldPrice = 0;
            optionsPrice.priceInclTax = 0;
            optionsPrice.priceExclTax = 0;
        }
        jQuery('#product-option').empty();
        jQuery('#product-option').append('<div class="price-box">Price: <span id="price-excluding-tax-' + itemId + '" class="regular-price">' + item.finalPrice + '</span><span class="including-tax"> Incl. Tax: </span><span id="price-including-tax-' + itemId + '" class="regular-price">' + item.finalPrice + '</span></div>');
        jQuery('#product-option').append(item.options);
        jQuery('#product-option').append('<div class="action"><button id="cancel" class="button cancel" type="button"><span>Cancel</span></button><button id="ok" class="ok" type="submit" ><span>OK</span></button></div>');
        jQuery('#product-option-form').bPopup({closeClass: 'button'});
        var productCompositeConfigureForm = new varienForm('product-option');
        jQuery("#product-option").unbind();
        jQuery("#product-option").submit(function( event ) {
            if (productCompositeConfigureForm.validate()){
                removeFromCat(id_row);
                jQuery('#product-option-form').bPopup().close();
                addConfigurableProduct(itemId);
            }
            event.preventDefault();
        });
        optionsPrice.reload();
    } else {
        addOrder(itemId, []);
        displayOrder(currentOrder, true);
    }
    auto_select_field();
    jQuery('#product-info li').removeClass('active');
    jQuery('#product-' + itemId).parent().parent().addClass('active');
    console.log("add to cart " + itemId);
    initScroll("#items_area");
}
//end change product bundle

function addConfigurableProduct(productId) {
    var options = jQuery('#product-option').serializeArray();
    addOrder(productId, options);
    displayOrder(currentOrder, true);
}

//create order item from itemId, option; auto create new if not find
function addOrder(productId, options) {
    var product = productData[productId];
    var oldOrderItem = null;
    var tempOrder = Object.keys(currentOrder);
    for (var i = 0; i < tempOrder.length; i++) {
        var tempOrderItem = currentOrder[tempOrder[i]];
        if (tempOrderItem.productId == productId) {
            oldOrderItem = tempOrderItem;
            if (options.length > 0) {
                //check option
                var currentProductOption = oldOrderItem.options;
                if (currentProductOption.compare(options)) {
                    oldOrderItem.qty++;
                    if (oldOrderItem.qty > product.qty){
                        alert('Current qty greater than stock qty of this product');
                    }
                    return;
                }
            }else{
                oldOrderItem.qty++;
                if (oldOrderItem.qty > product.qty){
                    alert('Current qty greater than stock qty of this product');
                }
                return;
            }
        }
    }

    //create order item
    var newOrderItem = {};
    newOrderItem.pos = Object.keys(currentOrder).length;
    newOrderItem.id = product.id+'-'+newOrderItem.pos;
    newOrderItem.productId = product.id;
    newOrderItem.name = product.name;
    newOrderItem.sku = product.sku;
    newOrderItem.tax = product.tax;
    if (isNaN(newOrderItem.tax)) {
        newOrderItem.tax = 0;
    }
    newOrderItem.price = product.finalPrice;
    newOrderItem.priceInclTax = product.priceInclTax;
    if (options.length > 0) {
        newOrderItem.price = parseFloat(jQuery('#price-excluding-tax-' + product.id).text().replace(priceFormat.groupSymbol, ''));
        newOrderItem.priceInclTax = parseFloat(jQuery('#price-including-tax-' + product.id).text().replace(priceFormat.groupSymbol, ''));
    }
    if (product.type=='grouped' && newOrderItem.price == 0){
        alert('You must select at least one product to add to cart');
        addToCart(productId);
        return;
    }

    newOrderItem.options = options;
    if (product.type=='bundle'){
        ProductConfigure.bundleControl.getOption(options,null);
    }else if (product.type=='configurable'){
        getConfigurableOption(options,config.attributes);
    }
    newOrderItem.qty = 1;
    if (product.qty <= 0 && product.type == 'simple'){
        alert('Currently this product is out of stock');
    }
    currentOrder[newOrderItem.id] = newOrderItem;
}

function removeFromCat(orderId) {
    var orderItem = currentOrder[orderId];
    orderItem.qty = 0;
    if (orderId.indexOf('-')>0){
        delete currentOrder[orderId];
    }
    displayOrder(currentOrder, true);
}

function changeQty(orderId) {
    var orderItem = currentOrder[orderId];
    var newQty = parseFloat(jQuery('#item-qty-' + orderId).val()).toFixed(2);
    if (newQty < 1 || isNaN(newQty)) {
        newQty = orderItem.qty;
        jQuery('#item-qty-' + orderId).val(newQty);
    }
    orderItem.qty = newQty;

    var product = productData[orderItem.productId];

    if (orderItem.qty > product.qty && product.type == 'simple'){
        alert('Current quantity greater than stock quantity of this product');
    }
    displayOrder(currentOrder, true);
}

function changePrice(orderId) {
    var orderItem = currentOrder[orderId];
    var newPrice = parseFloat(jQuery('#item-price-' + orderId).val());
    if (newPrice < 1 || isNaN(newPrice)) {
        newPrice = orderItem.price;
        jQuery('#item-price-' + orderId).val(newPrice);
    }
    var newPrice = (jQuery('#item-price-' + orderId).val());

    var split = newPrice.split("%");
    if(split.length==2){
        var value = parseFloat(split[0]);
        if (value < 0 || isNaN(value) || !onFlyDiscount || value>100) {
            value = orderItem.price;
            jQuery('#item-price-' + orderId).val(value);
            return;
        }
        //var percent = value%orderItem.price;
        var newValue = (100-parseFloat(value))*0.01*orderItem.price;
        jQuery('#item-price-' + orderId).val(newValue);
        orderItem.price = newValue;
        orderItem.priceInclTax = orderItem.price * (1 + orderItem.tax / 100);
        orderItem.price_name = 'item['+orderId+'][custom_price]';
        displayOrder(currentOrder, true);
        return;
    }else{
        var newPrice = parseFloat(jQuery('#item-price-' + orderId).val());
        if (newPrice < 0 || isNaN(newPrice) || !onFlyDiscount) {
            newPrice = orderItem.price;
            jQuery('#item-price-' + orderId).val(newPrice);
            return;
        }
        orderItem.price = newPrice;
        orderItem.priceInclTax = orderItem.price * (1 + orderItem.tax / 100);
        orderItem.price_name = 'item['+orderId+'][custom_price]';
        displayOrder(currentOrder, true);
    }
//    orderItem.price = newPrice;
//    orderItem.priceInclTax = orderItem.price * (1 + orderItem.tax / 100);
//    orderItem.price_name = 'item['+orderId+'][custom_price]';
//    displayOrder(currentOrder, true);
}

var orderTemplate = '<tr class="hover {class_item}-{id}" {style}><td><div class="item-first"><a href="#" class="remove" onclick="removeFromCat(\'{id}\')"></a><h5 class="hover1 {config_change}-{id}">{name}</h5>{option}</div></td><td class="qty align-center"><input id="item-qty-{id}" maxlength="12" value="{qty}" class="item-price input-text item-qty" name="item[{id}][qty]" onchange="changeQty(\'{id}\')"></td><td class="tax align-center">{tax}%</td><td class="price align-right"><input id="item-price-{id}" maxlength="12" value="{price}" class="input-text item-price" name="{price_name}" onchange="changePrice(\'{id}\')"></td><td class="subtotal align-right"><div class="subtotal-list">{subtotal}<span></span></div></td></tr>';
var noDiscountTemplate = '<tr class="hover {class_item}" {style}><td><div class="item-first"><a href="#" class="remove" onclick="removeFromCat(\'{id}\')"></a><h5>{name}</h5>{option}</div></td><td class="qty align-center"><input id="item-qty-{id}" maxlength="12" value="{qty}" class="input-text item-qty" name="item[{id}][qty]" onchange="changeQty(\'{id}\')"></td><td class="tax align-center">{tax}%</td><td class="price align-right"><span class="no-change">{price}</span></td><td class="subtotal align-right"><div class="subtotal-list">{subtotal}<span></span></div></td></tr>';
function displayOrder(order, updatePrice) {
    jQuery('#order-items_grid table tbody').empty();
    var itemNumber = parseFloat(0);
    var subtotal = parseFloat(0);
    var subtotalInclTax = parseFloat(0);

    jQuery.each(order, function (i, orderItem) {
        //calc
        orderItem.subtotal = orderItem.price * orderItem.qty;
        orderItem.subtotalInclTax = orderItem.priceInclTax * orderItem.qty;

        var orderOption = '';
        var class_item = orderItem.class_item;
        var config_change = orderItem.config_change;
        if (orderItem.options.length > 0) {
            jQuery.each(orderItem.options, function (i, option) {
                var optionName = option.name.replace('[', '][');
                var optionInput = '<input type="hidden" name="item[' + orderItem.id + '][' + optionName + '" value="' + option.value + '">';
                //optionInput+='<span class="option-title">'+option.optionTitle+': </span><span class="option-name">'+option.qty+'x '+option.title+'</span></li>';
                orderOption += optionInput;
            });
            config_change= "config_change item_config item_config-id";
            class_item = "config item_config item_config-id";
        }
        //orderOption+='</ul>';
        var tempOrderItem = {};
        if (orderItem.qty == 0) {
            tempOrderItem.style = 'style="display:none"';
        }

        tempOrderItem.id = orderItem.id;
        tempOrderItem.name = orderItem.name;
        tempOrderItem.option = orderOption;
        tempOrderItem.qty = orderItem.qty;
        tempOrderItem.tax = orderItem.tax;
        tempOrderItem.subtotal = formatCurrency(orderItem.subtotal, priceFormat);
        tempOrderItem.class_item = class_item;
        tempOrderItem.price_name = orderItem.price_name;
        tempOrderItem.config_change= config_change;

        if (displayTaxInShoppingCart){
            tempOrderItem.price = formatCurrency(orderItem.priceInclTax, priceFormat);
        }else{
            tempOrderItem.price = formatCurrency(orderItem.price, priceFormat);
        }

        if (displayTaxInSubtotal){
            tempOrderItem.subtotal = formatCurrency(orderItem.subtotalInclTax, priceFormat);
        }else{
            tempOrderItem.subtotal = formatCurrency(orderItem.subtotal, priceFormat);
        }

        var orderItemOutput = '';
        if (onFlyDiscount){
            orderItemOutput = nano(orderTemplate, tempOrderItem);
        }else{
            orderItemOutput = nano(noDiscountTemplate, tempOrderItem);
        }

        jQuery('#order-items_grid table tbody').append(orderItemOutput);

        itemNumber += parseFloat(orderItem.qty);
        subtotal += parseFloat(orderItem.subtotal);
        subtotalInclTax += parseFloat(orderItem.subtotalInclTax);
    })
    var tax = subtotalInclTax - subtotal;
    jQuery('#item_count_value').text(itemNumber);
    if (updatePrice) {
        if (displayTaxInSubtotal){
            jQuery('#subtotal_value').text(addCommas(subtotalInclTax.toFixed(2)));
        }else{
            jQuery('#subtotal_value').text(addCommas(subtotal.toFixed(2)));
        }

        jQuery('#tax_value').text(tax.toFixed(2));
        var discount = Math.abs(jQuery('#order_discount').val());

        var grandTotal = subtotalInclTax - discount;
        jQuery('#grandtotal').text(addCommas(grandTotal.toFixed(2)));
        jQuery('#cash-in').val(grandTotal.toFixed(2));
    }
    setTimeout(function () {
        jQuery('#items_area').getNiceScroll().doScrollPos(0,100000);
    }, 500);
    jQuery('#items_area').getNiceScroll().doScrollPos(0,100000);

}

function sortObject(obj) {
    var arr = [];
    var new_object = {};
    for (var prop in obj) {
        if (obj.hasOwnProperty(prop)) {
            arr.push({
                'key': prop,
                'sort': obj[prop].pos,
                'value': obj[prop]
            });
        }
    }
    //arr.sort(function(a, b) { return a.sort - b.sort; });
    arr.sort(function (a, b) {
        return b.sort - a.sort;
    });
    for (var i = 0; i < arr.length; i++) {
        new_object[arr[i].sort] = arr[i].value;
    }
    return new_object; // returns array
}

Array.prototype.compare = function (array) {
    // if the other array is a falsy value, return
    if (!array)
        return false;

    // compare lengths - can save a lot of time
    if (this.length != array.length)
        return false;
    for(var i = 0;i<this.length;i++){
        var a = this[i];
        var b = array[i];
        if (a.name != b.name || a.value != b.value){
            return false;
        }
    }
    //return JSON.stringify(this) == JSON.stringify(array);
    return true;
}
