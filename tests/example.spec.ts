import {test, expect} from '@playwright/test';
// @ts-ignore
import fs from 'fs';
// @ts-ignore
import path from 'path';

// Define an array of URLs to test
const urls = [
    /*
    {
        plugin: "WooPayments",
        url: "https://woo.com/products/woopayments",
    },
    {
        plugin: "Stripe",
        url: "https://woo.com/products/stripe",
    },
    {
        plugin: "WooCommerce Google Analytics",
        url: "https://woo.com/products/woocommerce-google-analytics/",
    },
    {
        plugin: "WooCommerce Tax",
        url: "https://woo.com/products/tax/",
    },
    {
        plugin: "Facebook for WooCommerce",
        url: "https://woo.com/products/facebook/",
    },
    {
        plugin: "Woo Subscriptions",
        url: "https://woo.com/products/woocommerce-subscriptions/",
    },
    {
        plugin: "Square",
        url: "https://woo.com/products/square/",
    },
    {
        plugin: "Product Add-Ons",
        url: "https://woo.com/products/product-add-ons",
    },
    {
        plugin: "Product Bundles",
        url: "https://woo.com/products/product-bundles/",
    },
    {
        plugin: "Shipment Tracking",
        url: "https://woo.com/products/shipment-tracking/",
    },
    {
        plugin: "WooCommerce Shipping",
        url: "https://woo.com/products/shipping/",
    },
    {
        plugin: "Min/Max Quantities",
        url: "https://woo.com/products/minmax-quantities/",
    },
    {
        plugin: "WooCommerce Bookings",
        url: "https://woo.com/products/woocommerce-bookings",
    },
    {
        plugin: "PayPal Braintree",
        url: "https://woo.com/products/woocommerce-gateway-paypal-powered-by-braintree/",
    },
    {
        plugin: "Gift Cards",
        url: "https://woo.com/products/gift-cards/",
    },
    {
        plugin: "AutomateWoo",
        url: "https://woo.com/products/automatewoo/",
    },
    {
        plugin: "WooCommerce Brands",
        url: "https://woo.com/products/brands/",
    },
    {
        plugin: "UPS Shipping Method",
        url: "https://woo.com/products/ups-shipping-method/",
    },
    {
        plugin: "USPS Shipping Method",
        url: "https://woo.com/products/usps-shipping-method/",
    },
    {
        plugin: "Checkout Field Editor",
        url: "https://woo.com/products/woocommerce-checkout-field-editor/",
    },
    {
        plugin: "Product Recommendations",
        url: "https://woo.com/products/product-recommendations/",
    },
    {
        plugin: "WooCommerce Purchase Order Gateway",
        url: "https://woo.com/products/woocommerce-gateway-purchase-order/",
    },
    {
        plugin: "ShipStation for WooCommerce",
        url: "https://woo.com/products/shipstation-integration/",
    },
    {
        plugin: "WooCommerce Block",
        url: "https://woo.com/products/woocommerce-gutenberg-products-block/",
    },
    {
        plugin: "Table Rate Shipping",
        url: "https://woo.com/products/table-rate-shipping/",
    },
    {
        plugin: "FedEx Shipping Method",
        url: "https://woo.com/products/fedex-shipping-module/",
    },
    {
        plugin: "WooCommerce Additional Variation Images",
        url: "https://woo.com/products/woocommerce-additional-variation-images/",
    },
    {
        plugin: "Pinterest for WooCommerce",
        url: "https://woo.com/products/pinterest-for-woocommerce/",
    },
    {
        plugin: "EU VAT Number",
        url: "https://woo.com/products/eu-vat-number/",
    },
    {
        plugin: "Back In Stock Notifications",
        url: "https://woo.com/products/back-in-stock-notifications/",
    },
    {
        plugin: "Product Vendors",
        url: "https://woo.com/products/product-vendors/",
    },
    {
        plugin: "WooCommerce Points and Rewards",
        url: "https://woo.com/products/woocommerce-points-and-rewards/",
    },
    {
        plugin: "Product CSV Import Suite",
        url: "https://woo.com/products/product-csv-import-suite/",
    },
    {
        plugin: "Australia Post Shipping Method",
        url: "https://woo.com/products/australia-post-shipping-method/",
    },
    {
        plugin: "WooCommerce One Page Checkout",
        url: "https://woo.com/products/woocommerce-one-page-checkout/",
    },
    {
        plugin: "Canada Post Shipping Method",
        url: "https://woo.com/products/canada-post-shipping-method/",
    },
    {
        plugin: "WooCommerce Pre-Orders",
        url: "https://woo.com/products/woocommerce-pre-orders/",
    },
    {
        plugin: "Royal Mail",
        url: "https://woo.com/products/royal-mail/",
    },
    {
        plugin: "WooCommerce Accommodation Bookings",
        url: "https://woo.com/products/woocommerce-accommodation-bookings/",
    },
    {
        plugin: "Composite Products",
        url: "https://woo.com/products/composite-products/",
    },
    {
        plugin: "WooCommerce Deposits",
        url: "https://woo.com/products/woocommerce-deposits/",
    },
    {
        plugin: "Xero",
        url: "https://woo.com/products/xero/",
    },
    {
        plugin: "WooCommerce Box Office",
        url: "https://woo.com/products/woocommerce-box-office/",
    },
    {
        plugin: "Conditional Shipping and Payments",
        url: "https://woo.com/products/conditional-shipping-and-payments/",
    },
    {
        plugin: "Payfast Payment Gateway",
        url: "https://woo.com/products/payfast-payment-gateway/",
    },
    {
        plugin: "WooCommerce Bookings Availability",
        url: "https://woo.com/products/bookings-availability/",
    },
    {
        plugin: "Returns and Warranty Requests",
        url: "https://woo.com/products/warranty-requests/",
    },
    {
        plugin: "Advanced Notification",
        url: "https://woo.com/products/advanced-notifications/",
    },
    {
        plugin: "All Products for Woo Subscriptions",
        url: "https://woo.com/products/all-products-for-woocommerce-subscriptions/",
    },
    {
        plugin: "Affirm Payments",
        url: "https://woo.com/products/woocommerce-gateway-affirm/",
    },
    {
        plugin: "Shipping Multiple Addresses",
        url: "https://woo.com/products/shipping-multiple-addresses/",
    },
    {
        plugin: "WooCommerce Order Barcodes",
        url: "https://woo.com/products/woocommerce-order-barcodes/",
    },
    {
        plugin: "Eway",
        url: "https://woo.com/products/eway/",
    },
     */
    {
        plugin: "Bulk Stock Management",
        url: "https://woo.com/products/bulk-stock-management/",
    },
    {
        plugin: "Woo Subscription Downloads",
        url: "https://woo.com/products/woocommerce-subscription-downloads/",
    },
    {
        plugin: "Per Product Shipping",
        url: "https://woo.com/products/per-product-shipping/",
    },
    {
        plugin: "AutomateWoo – Refer A Friend add-on",
        url: "https://woo.com/products/automatewoo-refer-a-friend/",
    },
    {
        plugin: "GoCardless",
        url: "https://woo.com/products/gocardless/",
    },
    {
        plugin: "WooCommerce Distance Rate Shipping",
        url: "https://woo.com/products/woocommerce-distance-rate-shipping/",
    },
    {
        plugin: "WooCommerce Stamps.com API",
        url: "https://woo.com/products/woocommerce-shipping-stamps/",
    },
    {
        plugin: "Gifting for Woo Subscriptions",
        url: "https://woo.com/products/woocommerce-subscriptions-gifting/",
    },
    {
        plugin: "AutomateWoo – Birthdays add-on",
        url: "https://woo.com/products/automatewoo-birthdays/",
    },
    {
        plugin: "Flat Rate Box Shipping",
        url: "https://woo.com/products/flat-rate-box-shipping/",
    },
    {
        plugin: "WooCommerce Coupon Campaigns",
        url: "https://woo.com/products/woocommerce-coupon-campaigns/",
    },
    {
        plugin: "WooCommerce Beta Tester",
        url: "https://woo.com/products/woocommerce-beta-tester/",
    },
    {
        plugin: "MailPoet – Newsletters, Email Marketing, and Automation",
        url: "https://woo.com/products/mailpoet/",
    },
    {
        plugin: "Google Listings & Ads",
        url: "https://woo.com/products/google-listings-and-ads/",
    },
    {
        plugin: "Sensei Pro (WC Paid Courses)",
        url: "https://woo.com/products/woocommerce-paid-courses/",
    },
    {
        plugin: "Sensei Pr",
        url: "https://woo.com/products/sensei-pro/",
    },
];

// Define the path to the results file
const resultsFilePath = path.join(__dirname, 'results.txt');

// Function to append results to the file
const appendToResultsFile = (content) => {
    fs.appendFileSync(resultsFilePath, `${content}\n`, 'utf8');
};

test.describe('Check for product title and pricing', () => {
    for (const url of urls) {
        test(`Check product rating of ${url.url}`, async ({page}) => {
            // Sleep for 1 second to avoid rate limiting
            await page.waitForTimeout(1000);

            await page.goto(url.url);
            const hasTitle = await page.locator('h1.wccom-product-title__product-name').isVisible();
            expect(hasTitle).toBeTruthy();

            if (hasTitle) {
                const averageRating = await page.locator('#reviews .wccom-ratings__average').textContent();
                const ratingCount = await page.locator('#reviews .wccom-ratings__rating-count').textContent();
                const ratingCountNumber = ratingCount;

                // Determine the result and append it to the results file
                const resultLine = `Plugin: ${url.plugin} - Average Rating: ${averageRating} - Rating Count: ${ratingCountNumber}`;
                appendToResultsFile(resultLine);

                // Log the result to the console
                console.log(resultLine);
            } else {
                const errorLine = `Product title not found at ${url}`;
                appendToResultsFile(errorLine);
                // Throw an error to fail the test
                throw new Error(errorLine);
            }
        });
    }
});