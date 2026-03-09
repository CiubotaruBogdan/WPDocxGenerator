<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$nonce = wp_create_nonce( 'dg_admin_nonce' );
$demo_url_simple = admin_url( 'admin-ajax.php?action=dg_download_demo&type=simple&nonce=' . $nonce );
$demo_url_table  = admin_url( 'admin-ajax.php?action=dg_download_demo&type=table&nonce=' . $nonce );
$new_template_url = admin_url( 'admin.php?page=document-generator-new' );
?>

<div class="wrap dg-wrap">
    <h1><?php esc_html_e( 'WPDocxGen Tutorial', 'document-generator' ); ?></h1>

    <div class="dg-tutorial">

        <!-- Quick Start -->
        <div class="dg-section">
            <h2>Quick Start</h2>
            <ol>
                <li>Create a <strong>.docx</strong> file in Microsoft Word or Google Docs (export as .docx)</li>
                <li>Add placeholders using the <code>#placeholder_name#</code> format wherever you want dynamic data</li>
                <li>Go to <a href="<?php echo esc_url( $new_template_url ); ?>"><strong>Add New Template</strong></a> and upload your file</li>
                <li>Map each detected placeholder to a WordPress field (user data, site info, custom fields, etc.)</li>
                <li>Save and copy the shortcode, then paste it on any page or post</li>
            </ol>
        </div>

        <!-- Demo Templates -->
        <div class="dg-section">
            <h2>Demo Templates</h2>
            <p>Download these ready-to-use templates to get started quickly. Upload them via <a href="<?php echo esc_url( $new_template_url ); ?>">Add New Template</a>.</p>

            <table class="wp-list-table widefat striped" style="max-width:800px;">
                <thead>
                    <tr>
                        <th>Template</th>
                        <th>Description</th>
                        <th>Placeholders</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Simple - User Profile</strong></td>
                        <td>A formatted document with headings, bold labels, and user/site data. No tables.</td>
                        <td>
                            <code>#user_firstname#</code> <code>#user_lastname#</code>
                            <code>#display_name#</code> <code>#user_email#</code>
                            <code>#user_role#</code> <code>#site_name#</code>
                            <code>#site_url#</code> <code>#current_date#</code>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $demo_url_simple ); ?>" class="button">
                                <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                                Download
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Table - Users Report</strong></td>
                        <td>A table that lists all WordPress users with repeating rows. Demonstrates the <code>#repeat:...#</code> syntax.</td>
                        <td>
                            <code>#site_name#</code> <code>#current_date#</code><br>
                            Table row: <code>#index#</code> <code>#display_name#</code>
                            <code>#user_email#</code> <code>#user_role#</code>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $demo_url_table ); ?>" class="button">
                                <span class="dashicons dashicons-download" style="vertical-align:middle;"></span>
                                Download
                            </a>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Placeholder Syntax -->
        <div class="dg-section">
            <h2>Placeholder Syntax</h2>

            <h3>Simple Placeholders</h3>
            <p>Use <code>#name#</code> format anywhere in your document. The plugin will replace them with real data when the user clicks the download button.</p>

            <table class="wp-list-table widefat striped" style="max-width:800px;">
                <thead><tr><th>In your .docx file</th><th>Gets replaced with</th></tr></thead>
                <tbody>
                    <tr><td><code>#user_firstname#</code></td><td>Current user's first name</td></tr>
                    <tr><td><code>#user_email#</code></td><td>Current user's email</td></tr>
                    <tr><td><code>#display_name#</code></td><td>Current user's display name</td></tr>
                    <tr><td><code>#user_role#</code></td><td>Current user's role(s)</td></tr>
                    <tr><td><code>#site_name#</code></td><td>WordPress site title</td></tr>
                    <tr><td><code>#site_url#</code></td><td>WordPress site URL</td></tr>
                    <tr><td><code>#current_date#</code></td><td>Today's date</td></tr>
                </tbody>
            </table>

            <p>You can use any combination of placeholders in the same document. Placeholders can appear in headings, paragraphs, headers, footers, and inside table cells.</p>
        </div>

        <!-- Repeating Table Rows -->
        <div class="dg-section">
            <h2>Repeating Table Rows (Dynamic Tables)</h2>

            <p>To create a table that generates multiple rows dynamically, use the <code>#repeat:blockname#</code> and <code>#endrepeat#</code> markers.</p>

            <h3>How it works</h3>
            <ol>
                <li>Create a table in your Word document</li>
                <li>Add a <strong>header row</strong> with your column titles (this row stays static)</li>
                <li>Add a <strong>data row</strong> with placeholders and the repeat markers</li>
                <li>The data row will be duplicated once for each record</li>
            </ol>

            <h3>Example: Users Table</h3>
            <p>Your Word document should contain a table like this:</p>

            <table class="wp-list-table widefat" style="max-width:800px;">
                <thead>
                    <tr style="background:#2b579a;color:#fff;">
                        <th style="color:#fff;">#</th>
                        <th style="color:#fff;">Name</th>
                        <th style="color:#fff;">Email</th>
                        <th style="color:#fff;">Role</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background:#f0f6ff;">
                        <td><code>#repeat:users#</code> <code>#index#</code></td>
                        <td><code>#display_name#</code></td>
                        <td><code>#user_email#</code></td>
                        <td><code>#user_role#</code> <code>#endrepeat#</code></td>
                    </tr>
                </tbody>
            </table>
            <p class="description" style="margin-top:8px;">The header row (blue) is static. The data row gets repeated for each user.</p>

            <h3>Rules</h3>
            <ul>
                <li><code>#repeat:blockname#</code> and <code>#endrepeat#</code> must be in the <strong>same table row</strong></li>
                <li>The block name (e.g., <code>users</code>) is what you'll see in the admin mapping screen</li>
                <li>Available placeholders inside repeat rows depend on the data source:
                    <ul>
                        <li><strong>WordPress Users:</strong> <code>#index#</code>, <code>#display_name#</code>, <code>#user_email#</code>, <code>#user_login#</code>, <code>#user_firstname#</code>, <code>#user_lastname#</code>, <code>#user_role#</code>, <code>#user_registered#</code></li>
                        <li><strong>Toolset Repeating Fields:</strong> depends on your Toolset field configuration</li>
                    </ul>
                </li>
                <li>You can filter WordPress Users by role in the "Extra / Custom Value" field (e.g., enter <code>administrator</code>)</li>
            </ul>
        </div>

        <!-- Field Sources -->
        <div class="dg-section">
            <h2>Available Field Sources</h2>

            <table class="wp-list-table widefat striped" style="max-width:800px;">
                <thead><tr><th>Source</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><strong>User Fields</strong></td><td>Data from the currently logged-in user (name, email, role, user meta, etc.)</td></tr>
                    <tr><td><strong>Site Fields</strong></td><td>WordPress site info (site name, URL, admin email, etc.)</td></tr>
                    <tr><td><strong>Post/Page Fields</strong></td><td>Data from the current post/page where the shortcode is placed</td></tr>
                    <tr><td><strong>Custom Text</strong></td><td>Static text you define in the "Extra / Custom Value" column</td></tr>
                    <tr><td><strong>Date/Time</strong></td><td>Current date/time in various formats</td></tr>
                    <tr><td><strong>CPT: ...</strong></td><td>Custom Post Type fields (auto-detected from your registered CPTs)</td></tr>
                    <tr><td><strong>Toolset Custom Fields</strong></td><td>Fields created with Toolset Types plugin</td></tr>
                    <tr><td><strong>Toolset Repeating Fields</strong></td><td>For tables: repeating field groups from Toolset (requires context post)</td></tr>
                    <tr><td><strong>WordPress Users</strong></td><td>For tables: lists all WordPress users as repeating rows</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Shortcode -->
        <div class="dg-section">
            <h2>Shortcode Usage</h2>

            <table class="wp-list-table widefat striped" style="max-width:800px;">
                <thead><tr><th>Shortcode</th><th>Description</th></tr></thead>
                <tbody>
                    <tr><td><code>[document_generator id="123"]</code></td><td>Basic usage - shows download button(s) as configured</td></tr>
                    <tr><td><code>[document_generator id="123" format="docx"]</code></td><td>Force DOCX-only download</td></tr>
                    <tr><td><code>[document_generator id="123" format="pdf"]</code></td><td>Force PDF-only download</td></tr>
                    <tr><td><code>[document_generator id="123" class="my-class"]</code></td><td>Add a custom CSS class to the button wrapper</td></tr>
                </tbody>
            </table>

            <p style="margin-top:10px;">The shortcode works on regular pages, posts, and <strong>Toolset Content Templates</strong> for custom post types.</p>
        </div>

        <!-- Tips -->
        <div class="dg-section">
            <h2>Tips</h2>
            <ul>
                <li><strong>Word splits placeholders:</strong> Sometimes Word breaks <code>#placeholder#</code> into separate XML runs (e.g., <code>#place</code> + <code>holder#</code>). The plugin auto-merges these, but if a placeholder is not detected, try retyping it in one go (without editing individual characters).</li>
                <li><strong>Formatting is preserved:</strong> Bold, italic, colors, fonts, and other formatting applied to placeholder text in Word will be kept in the generated document.</li>
                <li><strong>PDF conversion:</strong> Requires LibreOffice installed on the server (<code>apt-get install libreoffice</code>). Without it, only DOCX download is available.</li>
                <li><strong>Role restriction:</strong> Use the "Allowed Roles" setting to control which users can see and use the download button.</li>
            </ul>
        </div>

        <!-- Step by Step: Table Demo -->
        <div class="dg-section">
            <h2>Step-by-Step: Setting Up the Users Table Demo</h2>
            <ol>
                <li>Download the <a href="<?php echo esc_url( $demo_url_table ); ?>"><strong>Table - Users Report</strong></a> demo template above</li>
                <li>Go to <a href="<?php echo esc_url( $new_template_url ); ?>"><strong>Add New Template</strong></a></li>
                <li>Enter a name (e.g., "Users Report") and upload the demo .docx file</li>
                <li>The plugin will detect these placeholders:
                    <ul>
                        <li><code>#site_name#</code> &rarr; map to <strong>Site Fields</strong> &rarr; <strong>Site Name</strong></li>
                        <li><code>#current_date#</code> &rarr; map to <strong>Date/Time</strong> &rarr; <strong>Current Date</strong></li>
                    </ul>
                </li>
                <li>In the <strong>Repeating Table Blocks</strong> section, you'll see <code>#repeat:users#</code>:
                    <ul>
                        <li>Set source to <strong>WordPress Users</strong></li>
                        <li>Leave field empty for all users, or enter a role name to filter</li>
                    </ul>
                </li>
                <li>Click <strong>Save Template</strong></li>
                <li>Copy the shortcode and paste it on any page</li>
                <li>Visit the page and click the download button - you'll get a document with all users in a table!</li>
            </ol>
        </div>

    </div>
</div>

<style>
    .dg-tutorial .dg-section {
        background: #fff;
        border: 1px solid #ccd0d4;
        padding: 20px 25px;
        margin-bottom: 20px;
    }
    .dg-tutorial .dg-section h2 {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    .dg-tutorial .dg-section h3 {
        margin-top: 20px;
    }
    .dg-tutorial code {
        background: #f0f0f1;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 13px;
    }
    .dg-tutorial ol, .dg-tutorial ul {
        max-width: 750px;
    }
    .dg-tutorial li {
        margin-bottom: 6px;
        line-height: 1.6;
    }
    .dg-tutorial ul ul {
        margin-top: 6px;
    }
</style>
