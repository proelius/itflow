<?php

require_once '../../../includes/modal_header.php';

ob_start();

?>
<div class="modal-header bg-dark">
    <h5 class="modal-title"><i class="fa fa-fw fa-users me-2"></i>Importer Clients</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form action="post.php" method="post" enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <div class="modal-body">
        <p><strong>Importering Multiple Contacts:</strong><br>When importing a client, only one contact (which will become the primary contact) and one location (which will become the primary location) can be added initially. To add additional contacts, you will need to click into each client, navigate to the Contacts section, and import multiple contacts individually for each client.</p>
        <p><strong>Format csv file with headings & data:</strong><br>Client Name, Industry, Referral, Website, Primary Location Name, Location Phone, Location Ajouterress, City, State, Postal Code, Country, Primary Contact Name, Title, Contact Phone, Extension, Contact Mobile, Contact Email, Hourly Rate, Currency, Payment Terms, Tax ID, Abbreviation</p>
        <hr>
        <div class="mb-3 my-4">
            <input type="file" class="form-control" name="file" accept=".csv" required>
        </div>
        <hr>
        <div>Télécharger: <a class="text-bold" href="post.php?download_clients_csv_template">sample csv template</a></div>
    </div>
    <div class="modal-footer">
        <button type="submit" name="import_clients_csv" class="btn btn-primary text-strong"><i class="fas fa-upload me-2"></i>Importer</button>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="fa fa-times me-2"></i>Annuler</button>
    </div>
</form>

<?php
require_once '../../../includes/modal_footer.php';
