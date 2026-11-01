# User Guide & Operations Manual
## Moon Cake Bakery Management System

---

## 1. Authentication & Session Access

### 1.1 Logging In
1. Navigate to `http://localhost:8000/login` in your web browser.
2. Enter your username or email address and password.
3. Click **Sign In**.
4. The system validates credentials, checks for expired passwords, and redirects you to the designated role workflow:
   - **Administrators:** Redirected to the Executive Dashboard at `/admin/dashboard`.
   - **Managers & Cashiers:** Directed to the Point of Sale terminal at `/pos`.
   - **Bakers:** Directed to the Baking Production Schedule at `/admin/production`.

### 1.2 Logging Out
- Click the user profile avatar in the top-right corner of the application header.
- Select **Log Out**. Your authenticated session will be immediately invalidated.

---

## 2. Point of Sale (POS) Terminal

Located at `/pos` for Cashiers, Managers, and Administrators.

1. **Product Selection:**
   - Search products by name or SKU using the live search bar.
   - Click on category filter tabs (Cakes, Bread, Pastries, Cookies) to browse items.
   - Click a product tile to add it to the active order cart.
2. **Order Customization:**
   - Modify quantities in the cart with the `+` and `-` buttons.
   - Enter special baking or preparation instructions if required.
3. **Customer Lookup & Loyalty:**
   - Search for registered customers by phone number or name to attach them to the transaction.
   - Click **Quick Add** to register a new customer without leaving the POS screen.
4. **Checkout & Payment:**
   - Review subtotal, discount, and calculated sales tax.
   - Select the payment method: **Cash**, **Credit/Debit Card**, or **ABA Pay / KHQR**.
   - Enter tendered amount; the system calculates exact change due.
   - Click **Complete Order** to finalize. An invoice receipt is generated automatically.

---

## 3. Order Management & Invoices

Located at `/admin/orders`.

1. **Viewing Orders:** Inspect all counter and pre-ordered bakery items with customer names, totals, and fulfillment status.
2. **Order Lifecycle Pipeline:**
   - `Pending`: Order received and queued for kitchen preparation.
   - `Preparing / Baking`: Kitchen staff are actively preparing items.
   - `Ready for Pickup`: Baked goods are boxed and ready at the counter.
   - `Completed`: Customer has collected order and payment is settled.
   - `Cancelled`: Order aborted with automatic inventory rollback if applicable.
3. **Printing Receipts:** Click the **Receipt** button on any order to view or print the thermal receipt.

---

## 4. Kitchen Production & Baking Batches

Located at `/admin/production`.

1. **Scheduling a Batch:** Click **New Production Batch**, select the product, baking date, quantity to produce, and assigned baker.
2. **Completing a Batch:** When baking finishes, set the batch status to `Completed`.
3. **Automatic Inventory Depletion:** The system automatically references the product's formulation recipe and deducts the proportional quantity of flour, sugar, butter, and eggs from warehouse inventory.

---

## 5. Warehouse Inventory & Ingredients

Located at `/admin/ingredients`.

1. **Stock Overview:** View on-hand quantities, units of measure (kg, liters, pieces), and unit costs.
2. **Low-Stock Warnings:** Items at or below their minimum reorder threshold are highlighted in amber.
3. **Manual Adjustments:** Click **Adjust Stock** to log spoilage, restocks, or cycle count reconciliations with an audit reason.

---

## 6. Supplier Procurement & Purchase Orders

Located at `/admin/suppliers` and `/admin/purchase-orders`.

1. **Supplier Directory:** Manage ingredient vendor contact numbers, email addresses, and payment terms.
2. **Creating Purchase Orders:** Select a supplier, add ingredients, quantities, and specify the **Expected Delivery Date** (`expected_delivery_date`).
3. **Receiving Deliveries:** When the vendor delivers goods, mark the PO as `Received` to automatically increment warehouse inventory quantities.

---

## 7. Financial & Analytical Reports

Located at `/admin/reports`.

- **Daily Sales Summary:** Real-time revenue totals, total transaction counts, and average order value.
- **Payment Method Breakdown:** Compare cash vs card vs digital QR payment volumes.
- **Product Sales Performance:** Identify top-revenue items and slow-moving bakery products.
- **Exporting Reports:** Download summary reports as CSV or formatted Excel spreadsheets for accounting integration.

---

## 8. In-App Notifications

Located at `/admin/notifications`.

- Displays automated alerts: low inventory thresholds, upcoming product expiration warnings, and production batch alerts.
- Historical delivery notification links automatically redirect to `/admin/orders` with an explanatory notice.

