# Product Requirements Document: DIN Order Attach

**Status:** Approved design  
**Date:** 27 August 2026  
**Product:** WordPress / WooCommerce extension  
**Scope:** MVP

## 1. Summary

DIN Order Attach is a standalone WooCommerce extension that lets authorized internal staff upload multiple guest-post proof files to an order. The buyer can view and download every active file belonging to their order from the WooCommerce customer-note email and **My Account > Order details**.

The extension must use official WordPress and WooCommerce extension points. It must not modify WordPress core, WooCommerce core, or theme templates, so WooCommerce and theme updates do not remove the feature or its data.

## 2. Problem Statement

The current store flow starts when a buyer selects a pricing option and creates a WooCommerce order. After the guest post is published, internal staff need to upload several proof files, notify the buyer once, and keep those files available in the buyer's order dashboard.

WooCommerce does not provide this exact batch-upload and protected order-file workflow by default. Editing WooCommerce files would make the feature vulnerable to being overwritten by updates.

## 3. Goals

- Allow multiple proof files to be added to one WooCommerce order.
- Restrict upload and management to users allowed to manage WooCommerce orders.
- Give the buyer read-only access to files from their own order.
- Send one WooCommerce customer-note email after a new batch is saved.
- Include every active order file in that email.
- Require login and order ownership before a buyer can download a file.
- Preserve files and metadata across WordPress, WooCommerce, theme, and plugin updates.
- Support WooCommerce High-Performance Order Storage (HPOS).

## 4. Non-Goals

- Buyer uploads.
- Guest-checkout access.
- Public or passwordless download links.
- Physical file attachments in email.
- File versioning.
- Cloud-storage integration.
- A custom email system that replaces WooCommerce emails.
- Custom roles or a separate buyer dashboard.
- Automatic content analysis, image processing, or archive extraction.

## 5. Users and Permissions

### Administrator

- Upload multiple files.
- View and download files.
- Delete a file after explicit confirmation.

### Shop Manager

- Has the same attachment capabilities as Administrator through the standard WooCommerce order-management capability.

### Buyer

- Must have an account and be logged in.
- Can view and download active files only from orders owned by that account.
- Cannot upload, replace, or delete files.

### Other users and visitors

- Cannot view, download, upload, or delete order attachments.

Permission checks must use WooCommerce capabilities and order ownership. The implementation must not rely only on hard-coded role names.

## 6. Preconditions

- WooCommerce is installed and active.
- Checkout requires the buyer to log in or create an account.
- Each supported order is linked to a WordPress user account.
- WordPress email is configured. The current site uses WP Mail SMTP, but DIN Order Attach must remain independent of that plugin.
- Private storage is writable and is not directly accessible from the web.

Guest orders are outside the MVP. If a legacy order has no buyer account, the plugin must block the customer notification and show an admin warning.

## 7. Primary User Flow

1. The buyer logs in or creates an account.
2. The buyer selects pricing and creates a WooCommerce order.
3. The guest-post process is completed outside the attachment feature.
4. An Administrator or Shop Manager opens the WooCommerce order.
5. The staff member selects multiple proof files in the **DIN Order Attach** panel.
6. The staff member clicks **Update order**.
7. The plugin validates the complete batch.
8. If the batch is valid, the plugin stores all files and their metadata.
9. The plugin creates one automatic WooCommerce customer note.
10. WooCommerce triggers one customer-note email containing links to every active attachment on the order.
11. The buyer follows a link, logs in if necessary, and downloads the file after ownership verification.
12. The same active file list is available under **My Account > Order details**.

An order update without a new file must not trigger the attachment email.

## 8. Functional Requirements

### 8.1 Admin order panel

The plugin must add a **DIN Order Attach** panel to the WooCommerce order editor, including the HPOS order screen.

The panel must provide:

- A multi-file picker.
- A list of every active file on the order.
- Original filename.
- File type.
- File size.
- Uploading user.
- Upload timestamp.
- Download action.
- Delete action with explicit confirmation.
- Clear upload success and failure messages.

### 8.2 Upload policy

- One order can contain more than one file.
- Allowed extensions: PDF, DOC, DOCX, XLS, XLSX, JPG, PNG, and ZIP.
- Maximum size: 10 MB per file.
- Extension and detected MIME type must both be allowed.
- PHP, HTML, JavaScript, executable files, and every unlisted type must be rejected.
- Original filenames must be sanitized for display and metadata storage.
- Physical storage names must be generated by the system and must not reuse user-controlled filenames.

### 8.3 Atomic batch behavior

The batch is a single operation:

- All files are validated before any order metadata is committed.
- If any file is invalid or cannot be stored, the complete new batch is rejected.
- A rejected batch must not leave partial files or metadata.
- A rejected batch must not create a customer note or trigger an email.
- The admin error must identify the affected file and reason.

### 8.4 Buyer order details

Under **My Account > Order details**, the buyer must see every active attachment for that order, showing:

- Filename.
- File size.
- Upload date.
- **View/Download** action.

No upload or delete control may be rendered for the buyer.

### 8.5 Email behavior

After a valid new batch is saved with **Update order**:

- Create one automatic WooCommerce customer note.
- Use the standard WooCommerce customer-note email.
- Append a list of every active attachment on the order.
- Generate protected download links rather than attaching binaries to the email.
- Trigger only one email for the batch, regardless of its file count.

Any manually created **Note to customer** must also include the current active attachment list. This provides a native resend path if staff need to send the links again.

### 8.6 Download behavior

- A download URL must never reveal the physical storage path.
- An unauthenticated visitor must be sent to login and must not receive the file.
- After login, the endpoint must re-check access.
- A buyer may download only if the current account owns the order.
- A user with WooCommerce order-management capability may download for support purposes.
- Invalid, deleted, or unauthorized file requests must not disclose file existence or location.
- Responses must prevent MIME sniffing and browser execution of uploaded content.

### 8.7 Delete behavior

- Delete must require explicit confirmation.
- Successful deletion removes the file from private storage and order metadata.
- A deleted file disappears from the admin panel, buyer dashboard, and future emails.
- Old email links to a deleted file stop working.
- Deleting a file does not send a buyer email.

## 9. Data Model

Attachment metadata must be associated with the order through the `WC_Order` CRUD/meta API so that it works with both HPOS and legacy order storage.

Each attachment record contains:

- Unique attachment ID.
- Original sanitized filename.
- Internal private-storage reference.
- MIME type.
- File size in bytes.
- Uploading WordPress user ID.
- Upload timestamp.

Absolute public URLs must not be stored as the source of truth.

## 10. Architecture and Update Safety

- Package the feature as a standalone plugin named **DIN Order Attach**.
- Do not edit WordPress core, WooCommerce core, or theme files.
- Use WordPress and WooCommerce hooks, filters, nonce APIs, capability APIs, email APIs, and `WC_Order` CRUD methods.
- Declare HPOS compatibility.
- Keep attachment files outside the plugin directory and outside direct web access.
- Keep plugin settings minimal; allowed formats and the 10 MB limit are fixed MVP rules.
- Deactivation must preserve files and metadata.
- Plugin updates must preserve files and metadata.
- Uninstall must not erase files or metadata automatically.

## 11. Security Requirements

- Verify a valid nonce for every admin mutation.
- Verify the WooCommerce order-management capability for upload and deletion.
- Verify logged-in order ownership for buyer downloads.
- Sanitize input and escape every rendered filename and attribute.
- Validate both extension and MIME type.
- Generate non-user-controlled physical filenames.
- Never expose a direct storage URL.
- Stream authorized files through a controlled endpoint.
- Reject executable and unlisted types.
- Send safe download headers, including protection against content-type sniffing.

## 12. Failure and Recovery

- Invalid batch: reject the entire batch, preserve existing files, and do not send email.
- Storage error: roll back files written during that batch, preserve existing files, and show an admin error.
- Missing buyer account: preserve existing order data, block customer notification, and show an admin warning.
- Email-processing failure: keep the successfully stored files. Staff may resend by adding a WooCommerce **Note to customer**, which includes the full active file list.
- Unauthorized download: return a generic denial without exposing storage details.
- Missing or deleted file: return a generic not-found response and do not expose the former path.

The application can record that WordPress attempted to process an email. It cannot guarantee delivery to the buyer's inbox.

## 13. File Lifecycle

- Moving an order to Trash does not delete its attachment files.
- Permanently deleting an order removes its attachment files after the normal WordPress/WooCommerce confirmation.
- Deactivating or updating DIN Order Attach leaves all files and metadata intact.
- Uninstall leaves files and metadata intact unless a separate, explicitly confirmed cleanup feature is added in a future scope.

## 14. Compatibility Requirements

- Current verified local baseline: WordPress 7.1, WooCommerce 11.0.1, PHP 8.4.
- Must support WooCommerce HPOS.
- Must not depend on Elementor, the active theme, or WP Mail SMTP.
- Must degrade safely when WooCommerce is inactive: no order UI or attachment processing, and no fatal error.

## 15. Acceptance Criteria

1. Administrator and Shop Manager can upload multiple allowed files from an order.
2. Users without order-management capability cannot upload or delete files.
3. A buyer can view every active attachment on their own order details page.
4. A buyer cannot view attachments from another buyer's order.
5. Guests and direct storage URLs cannot retrieve a file.
6. A batch containing any invalid file is rejected without partial persistence.
7. A valid batch produces one automatic customer note and one customer-note email trigger.
8. The email and buyer dashboard list every active order attachment.
9. Updating order data without a new file does not trigger an attachment email.
10. Deleting a file requires confirmation and invalidates its old link.
11. WooCommerce updates do not remove the plugin feature, metadata, or files.
12. Plugin deactivation and reactivation preserve metadata and files.
13. The workflow works with HPOS enabled.
14. Guest orders do not receive attachment notifications and show a clear admin warning.

## 16. Minimum Verification Scenarios

- Upload two valid images in one batch and confirm one email trigger containing all active files.
- Upload one valid image plus one forbidden file and confirm neither new file is persisted.
- Upload a file larger than 10 MB and confirm the batch is rejected.
- Download as the owning buyer and confirm success.
- Attempt the same URL as another buyer and as a guest and confirm denial.
- Add an unrelated order change without a file and confirm no attachment email.
- Add a manual **Note to customer** and confirm it includes all active files.
- Delete one file after confirmation and confirm its old link fails.
- Enable HPOS and repeat upload, email, dashboard, and download checks.
- Deactivate and reactivate the plugin and confirm files remain accessible.
- Update WooCommerce in a test environment and confirm the plugin and stored files remain intact.

## 17. Success Definition

The MVP succeeds when internal staff can add a batch of guest-post proof files to an order using the normal WooCommerce order workflow, the buyer receives one standard WooCommerce notification containing all current files, and only authorized users can retrieve those files after updates to WooCommerce or the plugin.

