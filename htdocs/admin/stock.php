<?php
/* Copyright (C) 2006      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2008-2010 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2012-2013 Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2013-2018 Philippe Grand       <philippe.grand@atoo-net.com>
 * Copyright (C) 2013      Florian Henry        <florian.henry@open-concept.pro>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/admin/stock.php
 *	\ingroup    stock
 *	\brief      Page d'administration/configuration du module gestion de stock
 */
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/stock.lib.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

// Load translation files required by the page
$langs->loadLangs(array("admin", "stocks"));

// Securit check
if (!$user->admin) accessforbidden();

$action = GETPOST('action', 'aZ09');
$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'stock';


/*
 * Action
 */

$reg = array();

if (preg_match('/set_([a-z0-9_\-]+)/i', $action, $reg))
{
	$code = $reg[1];

	// If constant is for a unique choice, delete other choices
	if (in_array($code, array('STOCK_CALCULATE_ON_BILL', 'STOCK_CALCULATE_ON_VALIDATE_ORDER', 'STOCK_CALCULATE_ON_SHIPMENT', 'STOCK_CALCULATE_ON_SHIPMENT_CLOSE'))) {
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_BILL', $conf->entity);
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_VALIDATE_ORDER', $conf->entity);
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_SHIPMENT', $conf->entity);
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_SHIPMENT_CLOSE', $conf->entity);
	}
	if (in_array($code, array('STOCK_CALCULATE_ON_SUPPLIER_BILL', 'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER', 'STOCK_CALCULATE_ON_RECEPTION', 'STOCK_CALCULATE_ON_RECEPTION_CLOSE', 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER'))) {
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_SUPPLIER_BILL', $conf->entity);
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER', $conf->entity);
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_RECEPTION', $conf->entity);
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_RECEPTION_CLOSE', $conf->entity);
		dolibarr_del_const($db, 'STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER', $conf->entity);
	}

	if (dolibarr_set_const($db, $code, 1, 'chaine', 0, '', $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}

if (preg_match('/del_([a-z0-9_\-]+)/i', $action, $reg))
{
	$code = $reg[1];
	if (dolibarr_del_const($db, $code, $conf->entity) > 0)
	{
		header("Location: ".$_SERVER["PHP_SELF"]);
		exit;
	} else {
		dol_print_error($db);
	}
}

if ($action == 'warehouse')
{
	$value = GETPOST('default_warehouse', 'alpha');
	$res = dolibarr_set_const($db, "MAIN_DEFAULT_WAREHOUSE", $value, 'chaine', 0, '', $conf->entity);
	if ($value == -1 || empty($value) && !empty($conf->global->MAIN_DEFAULT_WAREHOUSE)){
		$res = dolibarr_del_const($db, "MAIN_DEFAULT_WAREHOUSE", $conf->entity);
	}
	if (!$res > 0) $error++;
}

if ($action == 'specimen')
{
	$modele = GETPOST('module', 'alpha');

	$object = new Entrepot($db);
	$object->initAsSpecimen();

	// Search template files
	$file = ''; $classname = ''; $filefound = 0;
	$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);
	foreach ($dirmodels as $reldir)
	{
		$file = dol_buildpath($reldir."core/modules/stock/doc/pdf_".$modele.".modules.php", 0);
		if (file_exists($file))
		{
			$filefound = 1;
			$classname = "pdf_".$modele;
			break;
		}
	}

	if ($filefound)
	{
		require_once $file;

		$module = new $classname($db);

		if ($module->write_file($object, $langs) > 0)
		{
			header("Location: ".DOL_URL_ROOT."/document.php?modulepart=stock&file=SPECIMEN.pdf");
			return;
		} else {
			setEventMessages($module->error, null, 'errors');
			dol_syslog($module->error, LOG_ERR);
		}
	} else {
		setEventMessages($langs->trans("ErrorModuleNotFound"), null, 'errors');
		dol_syslog($langs->trans("ErrorModuleNotFound"), LOG_ERR);
	}
}

// Activate a model
elseif ($action == 'set') {
	$ret = addDocumentModel($value, $type, $label, $scandir);
} elseif ($action == 'del') {
	$ret = delDocumentModel($value, $type);
	if ($ret > 0)
	{
		if ($conf->global->STOCK_ADDON_PDF == "$value") dolibarr_del_const($db, 'STOCK_ADDON_PDF', $conf->entity);
	}
}

// Set default model
elseif ($action == 'setdoc')
{
	if (dolibarr_set_const($db, "STOCK_ADDON_PDF", $value, 'chaine', 0, '', $conf->entity))
	{
		// The constant that was read before the new set
		// We therefore requires a variable to have a coherent view
		$conf->global->STOCK_ADDON_PDF = $value;
	}

	// On active le modele
	$ret = delDocumentModel($value, $type);
	if ($ret > 0)
	{
		$ret = addDocumentModel($value, $type, $label, $scandir);
	}
}


/*
 * View
 */

$form = new Form($db);
$dirmodels = array_merge(array('/'), (array) $conf->modules_parts['models']);

llxHeader('', $langs->trans("StockSetup"));

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("StockSetup"), $linkback, 'title_setup');

$head = stock_admin_prepare_head();

dol_fiche_head($head, 'general', $langs->trans("StockSetup"), -1, 'stock');

$form = new Form($db);
$formproduct = new FormProduct($db);



$disabled = '';
if (!empty($conf->productbatch->enabled))
{
	$langs->load("productbatch");
	$disabled = ' disabled';
	print info_admin($langs->trans("WhenProductBatchModuleOnOptionAreForced"));
}

//if (! empty($conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER) || ! empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT))
//{
print info_admin($langs->trans("IfYouUsePointOfSaleCheckModule"));
print '<br>';
//}


print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="warehouse">';

// Title rule for stock decrease
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print "<td>".$langs->trans("RuleForStockManagementDecrease")."</td>\n";
print '<td class="right">'.$langs->trans("Status").'</td>'."\n";
print '</tr>'."\n";

$found = 0;

print '<tr class="oddeven">';
print '<td>'.$langs->trans("DeStockOnBill").'</td>';
print '<td class="right">';
if (!empty($conf->facture->enabled))
{
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_BILL', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_BILL", $arrval, $conf->global->STOCK_CALCULATE_ON_BILL);
	}
} else {
	print $langs->trans("ModuleMustBeEnabledFirst", $langs->transnoentitiesnoconv("Module30Name"));
}
print "</td>\n</tr>\n";
$found++;


print '<tr class="oddeven">';
print '<td>'.$langs->trans("DeStockOnValidateOrder").'</td>';
print '<td class="right">';
if (!empty($conf->commande->enabled))
{
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_VALIDATE_ORDER', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_VALIDATE_ORDER", $arrval, $conf->global->STOCK_CALCULATE_ON_VALIDATE_ORDER);
	}
} else {
	print $langs->trans("ModuleMustBeEnabledFirst", $langs->transnoentitiesnoconv("Module25Name"));
}
print "</td>\n</tr>\n";
$found++;

//if (! empty($conf->expedition->enabled))
//{

print '<tr class="oddeven">';
print '<td>'.$langs->trans("DeStockOnShipment").'</td>';
print '<td class="right">';
if (!empty($conf->expedition->enabled))
{
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_SHIPMENT', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_SHIPMENT", $arrval, $conf->global->STOCK_CALCULATE_ON_SHIPMENT);
	}
} else {
	print $langs->trans("ModuleMustBeEnabledFirst", $langs->transnoentitiesnoconv("Module80Name"));
}
print "</td>\n</tr>\n";
$found++;


print '<tr class="oddeven">';
print '<td>'.$langs->trans("DeStockOnShipmentOnClosing").'</td>';
print '<td class="right">';
if (!empty($conf->expedition->enabled))
{
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_SHIPMENT_CLOSE', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_SHIPMENT_CLOSE", $arrval, $conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE);
	}
} else {
	print $langs->trans("ModuleMustBeEnabledFirst", $langs->transnoentitiesnoconv("Module80Name"));
}
print "</td>\n</tr>\n";
$found++;

print '</table>';


print '<br>';


// Title rule for stock increase
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print "<td>".$langs->trans("RuleForStockManagementIncrease")."</td>\n";
print '<td class="right">'.$langs->trans("Status").'</td>'."\n";
print '</tr>'."\n";

$found = 0;

print '<tr class="oddeven">';
print '<td>'.$langs->trans("ReStockOnBill").'</td>';
print '<td class="right">';
if (!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD) || !empty($conf->supplier_order->enabled) || !empty($conf->supplier_invoice->enabled))
{
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_SUPPLIER_BILL', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_SUPPLIER_BILL", $arrval, $conf->global->STOCK_CALCULATE_ON_SUPPLIER_BILL);
	}
} else {
	print $langs->trans("ModuleMustBeEnabledFirst", $langs->transnoentitiesnoconv("Module40Name"));
}
print "</td>\n</tr>\n";
$found++;



print '<tr class="oddeven">';
print '<td>'.$langs->trans("ReStockOnValidateOrder").'</td>';
print '<td class="right">';
if (!empty($conf->fournisseur->enabled) && empty($conf->global->MAIN_USE_NEW_SUPPLIERMOD) || !empty($conf->supplier_order->enabled) || !empty($conf->supplier_invoice->enabled))
{
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER", $arrval, $conf->global->STOCK_CALCULATE_ON_SUPPLIER_VALIDATE_ORDER);
	}
} else {
	print $langs->trans("ModuleMustBeEnabledFirst", $langs->transnoentitiesnoconv("Module40Name"));
}
print "</td>\n</tr>\n";
$found++;

if (!empty($conf->reception->enabled))
{
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("StockOnReception").'</td>';
	print '<td class="right">';

	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_RECEPTION', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_RECEPTION", $arrval, $conf->global->STOCK_CALCULATE_ON_RECEPTION);
	}

	print "</td>\n</tr>\n";
	$found++;


	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("StockOnReceptionOnClosing").'</td>';
	print '<td class="right">';

	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_CALCULATE_ON_RECEPTION_CLOSE', array(), null, 0, 0, 0, 2, 1);
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_CALCULATE_ON_RECEPTION_CLOSE", $arrval, $conf->global->STOCK_CALCULATE_ON_RECEPTION_CLOSE);
	}
	print "</td>\n</tr>\n";
	$found++;
} else {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("ReStockOnDispatchOrder").'</td>';
	print '<td class="right">';
	if (!empty($conf->fournisseur->enabled))
	{
		if ($conf->use_javascript_ajax) {
			print ajax_constantonoff('STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER', array(), null, 0, 0, 0, 2, 1);
		} else {
			$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
			print $form->selectarray("STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER", $arrval, $conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER);
		}
	} else {
		print $langs->trans("ModuleMustBeEnabledFirst", $langs->transnoentitiesnoconv("Module40Name"));
	}
	print "</td>\n</tr>\n";
	$found++;
}

print '</table>';

print '<br>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print "<td>".$langs->trans("RuleForStockAvailability")."</td>\n";
print '<td class="right">'.$langs->trans("Status").'</td>'."\n";
print '</tr>'."\n";


print '<tr class="oddeven">';
print '<td>'.$langs->trans("WarehouseAllowNegativeTransfer").'</td>';
print '<td class="right">';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('STOCK_ALLOW_NEGATIVE_TRANSFER');
} else {
	$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
	print $form->selectarray("STOCK_ALLOW_NEGATIVE_TRANSFER", $arrval, $conf->global->STOCK_ALLOW_NEGATIVE_TRANSFER);
}
print "</td>\n";
print "</tr>\n";

// Option to force stock to be enough before adding a line into document
if ($conf->invoice->enabled)
{
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("StockMustBeEnoughForInvoice").'</td>';
	print '<td class="right">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_MUST_BE_ENOUGH_FOR_INVOICE');
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_MUST_BE_ENOUGH_FOR_INVOICE", $arrval, $conf->global->STOCK_MUST_BE_ENOUGH_FOR_INVOICE);
	}
	print "</td>\n";
	print "</tr>\n";
}

if ($conf->order->enabled)
{
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("StockMustBeEnoughForOrder").'</td>';
	print '<td class="right">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_MUST_BE_ENOUGH_FOR_ORDER');
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_MUST_BE_ENOUGH_FOR_ORDER", $arrval, $conf->global->STOCK_MUST_BE_ENOUGH_FOR_ORDER);
	}
	print "</td>\n";
	print "</tr>\n";
}

if ($conf->expedition->enabled)
{
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("StockMustBeEnoughForShipment").'</td>';
	print '<td class="right">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT');
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT", $arrval, $conf->global->STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT);
	}
	print "</td>\n";
	print "</tr>\n";
}
print '</table>';

print '<br>';

$virtualdiffersfromphysical = 0;
if (!empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT)
	|| !empty($conf->global->STOCK_CALCULATE_ON_SUPPLIER_DISPATCH_ORDER)
	|| !empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE)
	|| !empty($conf->global->STOCK_CALCULATE_ON_RECEPTION)
	|| !empty($conf->global->STOCK_CALCULATE_ON_RECEPTION_CLOSE)
	|| !empty($conf->mrp->enabled))
{
	$virtualdiffersfromphysical = 1; // According to increase/decrease stock options, virtual and physical stock may differs.
}

if ($virtualdiffersfromphysical)
{
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print "<td>".$langs->trans("RuleForStockReplenishment")." ".img_help('help', $langs->trans("VirtualDiffersFromPhysical"))."</td>\n";
	print '<td class="right">'.$langs->trans("Status").'</td>'."\n";
	print '</tr>'."\n";

	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("UseVirtualStockByDefault").'</td>';
	print '<td class="right">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_USE_VIRTUAL_STOCK');
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_USE_VIRTUAL_STOCK", $arrval, $conf->global->STOCK_USE_VIRTUAL_STOCK);
	}
	print "</td>\n";
	print "</tr>\n";
	print '</table>';

	print '<br>';
}

print '<form>';


print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="warehouse">';


/*
 * Document templates generators
 */

print load_fiche_titre($langs->trans("WarehouseModelModules"), '', '');

// Load array def with activated templates
$def = array();
$sql = "SELECT nom";
$sql .= " FROM ".MAIN_DB_PREFIX."document_model";
$sql .= " WHERE type = '".$type."'";
$sql .= " AND entity = ".$conf->entity;
$resql = $db->query($sql);
if ($resql)
{
	$i = 0;
	$num_rows = $db->num_rows($resql);
	while ($i < $num_rows)
	{
		$array = $db->fetch_array($resql);
		array_push($def, $array[0]);
		$i++;
	}
} else {
	dol_print_error($db);
}


print "<table class=\"noborder\" width=\"100%\">\n";
print "<tr class=\"liste_titre\">\n";
print '<td>'.$langs->trans("Name").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="center" width="60">'.$langs->trans("Status")."</td>\n";
print '<td class="center" width="60">'.$langs->trans("Default")."</td>\n";
print '<td class="center" width="38">'.$langs->trans("ShortInfo").'</td>';
print '<td class="center" width="38">'.$langs->trans("Preview").'</td>';
print "</tr>\n";

clearstatcache();

foreach ($dirmodels as $reldir)
{
	foreach (array('', '/doc') as $valdir)
	{
		$realpath = $reldir."core/modules/stock".$valdir;
		$dir = dol_buildpath($realpath);

		if (is_dir($dir))
		{
			$handle = opendir($dir);
			if (is_resource($handle))
			{
				while (($file = readdir($handle)) !== false)
				{
					$filelist[] = $file;
				}
				closedir($handle);
				arsort($filelist);

				foreach ($filelist as $file)
				{
					if (preg_match('/\.modules\.php$/i', $file) && preg_match('/^(pdf_|doc_)/', $file))
					{
						if (file_exists($dir.'/'.$file))
						{
							$name = substr($file, 4, dol_strlen($file) - 16);
							$classname = substr($file, 0, dol_strlen($file) - 12);

							require_once $dir.'/'.$file;
							$module = new $classname($db);

							$modulequalified = 1;
							if ($module->version == 'development' && $conf->global->MAIN_FEATURES_LEVEL < 2) $modulequalified = 0;
							if ($module->version == 'experimental' && $conf->global->MAIN_FEATURES_LEVEL < 1) $modulequalified = 0;

							if ($modulequalified)
							{
								print '<tr class="oddeven"><td width="100">';
								print (empty($module->name) ? $name : $module->name);
								print "</td><td>\n";
								if (method_exists($module, 'info')) print $module->info($langs);
								else print $module->description;
								print '</td>';

								// Active
								if (in_array($name, $def))
								{
									print '<td class="center">'."\n";
									print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=del&value='.$name.'">';
									print img_picto($langs->trans("Enabled"), 'switch_on');
									print '</a>';
									print '</td>';
								} else {
									print '<td class="center">'."\n";
									print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=set&value='.$name.'&amp;scan_dir='.$module->scandir.'&amp;label='.urlencode($module->name).'">'.img_picto($langs->trans("Disabled"), 'switch_off').'</a>';
									print "</td>";
								}

								// Default
								print '<td class="center">';
								if ($conf->global->STOCK_ADDON_PDF == $name)
								{
									print img_picto($langs->trans("Default"), 'on');
								} else {
									print '<a class="reposition" href="'.$_SERVER["PHP_SELF"].'?action=setdoc&value='.$name.'&amp;scan_dir='.$module->scandir.'&amp;label='.urlencode($module->name).'" alt="'.$langs->trans("Default").'">'.img_picto($langs->trans("Disabled"), 'off').'</a>';
								}
								print '</td>';

								// Info
								$htmltooltip = ''.$langs->trans("Name").': '.$module->name;
								$htmltooltip .= '<br>'.$langs->trans("Type").': '.($module->type ? $module->type : $langs->trans("Unknown"));
								if ($module->type == 'pdf')
								{
									$htmltooltip .= '<br>'.$langs->trans("Width").'/'.$langs->trans("Height").': '.$module->page_largeur.'/'.$module->page_hauteur;
								}
								$htmltooltip .= '<br>'.$langs->trans("Path").': '.preg_replace('/^\//', '', $realpath).'/'.$file;

								$htmltooltip .= '<br><br><u>'.$langs->trans("FeaturesSupported").':</u>';
								$htmltooltip .= '<br>'.$langs->trans("Logo").': '.yn($module->option_logo, 1, 1);
								$htmltooltip .= '<br>'.$langs->trans("MultiLanguage").': '.yn($module->option_multilang, 1, 1);


								print '<td class="center">';
								print $form->textwithpicto('', $htmltooltip, 1, 0);
								print '</td>';

								// Preview
								print '<td class="center">';
								if ($module->type == 'pdf')
								{
									print '<a href="'.$_SERVER["PHP_SELF"].'?action=specimen&module='.$name.'">'.img_object($langs->trans("Preview"), 'bill').'</a>';
								} else {
									print img_object($langs->trans("PreviewNotAvailable"), 'generic');
								}
								print '</td>';

								print "</tr>\n";
							}
						}
					}
				}
			}
		}
	}
}

print '</table>';

print '</form>';


// Other

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="warehouse">';

print load_fiche_titre($langs->trans("Other"), '', '');

print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print "<td>".$langs->trans("Other")."</td>\n";
print '<td class="right">'.$langs->trans("Status").'</td>'."\n";
print '</tr>'."\n";

print '<tr class="oddeven">';
print '<td>'.$langs->trans("MainDefaultWarehouse").'</td>';
print '<td class="right">';
print $formproduct->selectWarehouses($conf->global->MAIN_DEFAULT_WAREHOUSE, 'default_warehouse', '', 1, 0, 0, '', 0, 0, array(), 'left reposition');
print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
print "</td>";
print "</tr>\n";

print '<tr class="oddeven">';
print '<td>'.$langs->trans("UserDefaultWarehouse").'</td>';
print '<td class="right">';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('MAIN_DEFAULT_WAREHOUSE_USER', array(), null,  0, 0, 1);
} else {
	$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
	print $form->selectarray("MAIN_DEFAULT_WAREHOUSE_USER", $arrval, $conf->global->MAIN_DEFAULT_WAREHOUSE_USER);
}
print "</td>\n";
print "</tr>\n";

if (! empty($conf->global->MAIN_DEFAULT_WAREHOUSE_USER)) {
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("UserWarehouseAutoCreate").'</td>';
	print '<td class="right">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('STOCK_USERSTOCK_AUTOCREATE');
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("STOCK_USERSTOCK_AUTOCREATE", $arrval, $conf->global->STOCK_USERSTOCK_AUTOCREATE);
	}
	print "</td>\n";
	print "</tr>\n";
}

print '<tr class="oddeven">';
print '<td>'.$langs->trans("WarehouseAskWarehouseDuringOrder").'</td>';
print '<td class="right">';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('WAREHOUSE_ASK_WAREHOUSE_DURING_ORDER');
} else {
	$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
	print $form->selectarray("WAREHOUSE_ASK_WAREHOUSE_DURING_ORDER", $arrval, $conf->global->WAREHOUSE_ASK_WAREHOUSE_DURING_ORDER);
}
print "</td>";
print '</td>';
print "</tr>\n";

print '<tr class="oddeven">';
print '<td>';
print $form->textwithpicto($langs->trans("StockSupportServices"), $langs->trans("StockSupportServicesDesc"));
print '</td>';
print '<td class="right">';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('STOCK_SUPPORTS_SERVICES');
} else {
	$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
	print $form->selectarray("STOCK_SUPPORTS_SERVICES", $arrval, $conf->global->STOCK_SUPPORTS_SERVICES);
}
print "</td>\n";
print "</tr>\n";

print '<tr class="oddeven">';
print '<td>'.$langs->trans("AllowAddLimitStockByWarehouse").'</td>';
print '<td class="right">';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE');
} else {
	$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
	print $form->selectarray("STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE", $arrval, $conf->global->STOCK_ALLOW_ADD_LIMIT_STOCK_BY_WAREHOUSE);
}
print "</td>\n";
print "</tr>\n";

print '<tr class="oddeven">';
print '<td>'.$langs->trans("AlwaysShowFullArbo").'</td>';
print '<td class="right">';
if ($conf->use_javascript_ajax) {
	print ajax_constantonoff('STOCK_ALWAYS_SHOW_FULL_ARBO');
} else {
	$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
	print $form->selectarray("STOCK_ALWAYS_SHOW_FULL_ARBO", $arrval, $conf->global->STOCK_ALWAYS_SHOW_FULL_ARBO);
}
print "</td>\n";
print "</tr>\n";
print '</table>';

print '</form>';

/*
print '<br>';
if ($conf->global->MAIN_FEATURES_LEVEL >= 2)
{
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Inventory").'</td>'."\n";
	print '<td class="right">'.$langs->trans("Status").'</td>'."\n";
	print '</tr>'."\n";

	// Example with a yes / no select
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("INVENTORY_DISABLE_VIRTUAL").'</td>';
	print '<td class="center">';
	if ($conf->use_javascript_ajax) {
		print ajax_constantonoff('INVENTORY_DISABLE_VIRTUAL');
	} else {
		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
		print $form->selectarray("INVENTORY_DISABLE_VIRTUAL", $arrval, $conf->global->INVENTORY_DISABLE_VIRTUAL);
	}
	print '</td></tr>';


	// Example with a yes / no select
    print '<tr class="oddeven">';
	print '<td>'.$langs->trans("INVENTORY_USE_MIN_PA_IF_NO_LAST_PA").'</td>';
	print '<td class="center">';
  	if ($conf->use_javascript_ajax) {
  		print ajax_constantonoff('INVENTORY_USE_MIN_PA_IF_NO_LAST_PA');
  	} else {
  		$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
  		print $form->selectarray("INVENTORY_USE_MIN_PA_IF_NO_LAST_PA", $arrval, $conf->global->INVENTORY_USE_MIN_PA_IF_NO_LAST_PA);
  	}
  	print '</td></tr>';


  	// Example with a yes / no select
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("INVENTORY_USE_INVENTORY_DATE_FOR_DATE_OF_MVT").'</td>';
	print '<td class="right">';
	if ($conf->use_javascript_ajax) {
    	print ajax_constantonoff('INVENTORY_USE_INVENTORY_DATE_FOR_DATE_OF_MVT');
	} else {
    	$arrval = array('0' => $langs->trans("No"), '1' => $langs->trans("Yes"));
    	print $form->selectarray("INVENTORY_USE_INVENTORY_DATE_FOR_DATE_OF_MVT", $arrval, $conf->global->INVENTORY_USE_INVENTORY_DATE_FOR_DATE_OF_MVT);
	}
	print '</td></tr>';

	print '</table>';
}
*/

/* I keep the option/feature, but hidden to end users for the moment. If feature is used by module, no need to have users see it.
If not used by a module, I still need to understand in which case user may need this now we can set rule on product page.
if ($conf->global->PRODUIT_SOUSPRODUITS)
{
	print '<tr class="oddeven">';
	print '<td>'.$langs->trans("IndependantSubProductStock").'</td>';
	print '<td class="right">';
	print "<form method=\"post\" action=\"stock.php\">";
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print "<input type=\"hidden\" name=\"action\" value=\"INDEPENDANT_SUBPRODUCT_STOCK\">";
	print $form->selectyesno("INDEPENDANT_SUBPRODUCT_STOCK",$conf->global->INDEPENDANT_SUBPRODUCT_STOCK,1);
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'">';
	print '</form>';
	print "</td>\n";
	print "</tr>\n";
}
*/

// End of page
llxFooter();
$db->close();
