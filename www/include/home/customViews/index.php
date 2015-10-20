<?php
/**
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once $centreon_path . 'www/class/centreonCustomView.class.php';
require_once $centreon_path . "www/class/centreonWidget.class.php";
require_once $centreon_path . "www/class/centreonContactgroup.class.php";

/**
 * Quickform
 */
require_once 'HTML/QuickForm.php';
require_once 'HTML/QuickForm/advmultiselect.php';
require_once 'HTML/QuickForm/Renderer/ArraySmarty.php';

try {
    $db = new CentreonDB();
    $viewObj = new CentreonCustomView($centreon, $db);

    /*
	 * Smarty
	 */
    $path = "./include/home/customViews/";
    
    /*
     * Smarty INIT
     */
    $template = new Smarty();
    $template = initSmartyTpl($path, $template, "./");

    $viewId = $viewObj->getCurrentView();
    $views = $viewObj->getCustomViews();

    $rotationTimer = 0;
    if (isset($_SESSION['rotation_timer'])) {
        $rotationTimer = $_SESSION['rotation_timer'];
    }

    $i = 1;
    $indexTab = array(0 => -1);

    foreach ($views as $key => $val) {
    	$indexTab[$key] = $i;
        $i++;
        if (!$viewObj->checkPermission($key)) {
            $views[$key]['icon'] = "locked";
        } else {
            $views[$key]['icon'] = "unlocked";
		}
		$views[$key]['default'] = "";
		if ($viewObj->getDefaultViewId() == $key) {
			$views[$key]['default'] = sprintf(" (%s)", _('default'));
			$views[$key]['default'] = '<span class="ui-icon ui-icon-star" style="float:left;"></span>';
		}
    }
    $template->assign('views', $views);
    $template->assign('empty', $i);
    $template->assign('msg', _("No view available. To create a new view, please click \"Add view\" button."));

    $formAddView = new HTML_QuickForm('formAddView', 'post', "?p=103");
    $formAddView->addElement('header', 'title', _("Create a view"));
    $formAddView->addElement('header', 'information', _("General Information"));


    $query = "select * from custom_views where public = 1";
    $DBRES = $db->query($query);
    $arrayView = array();
    $arrayView[-1] = "";
    while($row = $DBRES->fetchRow()) {
        $arrayView[$row['custom_view_id']] = $row['name'];
    }

    $formAddView->addElement('select', 'viewLoad', _("Public views list"),$arrayView );
    /**
     * Name
     */
    $formAddView->addElement('text', 'name', _("Name"), $attrsText);

    $createLoad = array();
    $createLoad[] = HTML_QuickForm::createElement('radio', 'create_load', null, _("Create new view "), 'create');
    $createLoad[] = HTML_QuickForm::createElement('radio', 'create_load', null, _("Load from existing view"), 'load');
    $formAddView->addGroup($createLoad, 'create_load', _("create or load"), '&nbsp;');
    $formAddView->setDefaults(array('create_load[create_load]' => 'create'));

    /**
     * Layout
     */
    $layouts[] = HTML_QuickForm::createElement('radio', 'layout', null, _("1 Column"), 'column_1');
    $layouts[] = HTML_QuickForm::createElement('radio', 'layout', null, _("2 Columns"), 'column_2');
    $layouts[] = HTML_QuickForm::createElement('radio', 'layout', null, _("3 Columns"), 'column_3');
    $formAddView->addGroup($layouts, 'layout', _("Layout"), '&nbsp;');
    if ($action == "add") {
        $formAddView->setDefaults(array('layout[layout]' => 'column_1'));
    }

    $formAddView->addElement('checkbox', 'public', _("Public"), $attrsText);

    /**
     * Submit button
     */
    $formAddView->addElement('button', 'submit', _("Submit"), array("onClick" => "submitAddView();","class" => "btc bt_success"));
    $formAddView->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
    $formAddView->addElement('hidden', 'action');
    $formAddView->setDefaults(array('action' => 'add'));

    /**
     * Renderer
     */
    $rendererAddView = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererAddView->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererAddView->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formAddView->accept($rendererAddView);
    $template->assign('formAddView', $rendererAddView->toArray());
    
    /**
     * Form for edit view
     */
    $formEditView = new HTML_QuickForm('formEditView', 'post', "?p=103");
    $formEditView->addElement('header', 'title', _('Edit a view'));
    $formEditView->addElement('header', 'information', _("General Information"));

    $template->assign('editMode', _("Show/Hide edit mode"));

    /**
     * Name
     */
    $formEditView->addElement('text', 'name', _("Name"), $attrsText);
    
    /**
     * Layout
     */
    $layouts = array();
    $layouts[] = HTML_QuickForm::createElement('radio', 'layout', null, _("1 Column"), 'column_1');
    $layouts[] = HTML_QuickForm::createElement('radio', 'layout', null, _("2 Columns"), 'column_2');
    $layouts[] = HTML_QuickForm::createElement('radio', 'layout', null, _("3 Columns"), 'column_3');
    $formEditView->addGroup($layouts, 'layout', _("Layout"), '&nbsp;');
    $formEditView->setDefaults(array('layout[layout]' => 'column_1'));

    $formEditView->addElement('checkbox', 'public', _("Public"), $attrsText);

    /**
     * Submit button
     */
    $formEditView->addElement('button', 'submit', _("Submit"), array("onClick" => "submitEditView();","class" => "btc bt_success"));
    $formEditView->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
    $formEditView->addElement('hidden', 'action');
    $formEditView->addElement('hidden', 'custom_view_id');
    $formEditView->setDefaults(array('action' => 'edit'));

    /**
     * Renderer
     */
    $rendererEditView = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererEditView->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererEditView->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formEditView->accept($rendererEditView);
    $template->assign('formEditView', $rendererEditView->toArray());

    /**
     * Form share view
     */
    $cgObj = new CentreonContactgroup($db);
    $formShareView = new HTML_QuickForm('formShareView', 'post', "?p=103");
    $formShareView->addElement('header', 'title', _("Share view"));
    $formShareView->addElement('header', 'information', _("General Information"));

    /**
     * Locked
     */
    $locked[] = HTML_QuickForm::createElement('radio', 'locked', null, _("Yes"), '1');
    $locked[] = HTML_QuickForm::createElement('radio', 'locked', null, _("No"), '0');
    $formShareView->addGroup($locked, 'locked', _("Locked?"), '&nbsp;');
    $formShareView->setDefaults(array('locked' => '1'));

    /**
     * Get viewers
     */
    /*$viewers = $viewObj->getUsersFromViewId($viewId);
    $viewerGroups = $viewObj->getUsergroupsFromViewId($viewId); */

    /**
     * Users
     */
    //$userList = array_diff_key($centreon->user->getUserList($db), $viewers);
    $ams1 = $formShareView->addElement('advmultiselect', 'user_id', array(_("User List"), _("Available"), _("Selected")), $centreon->user->getUserList($db), $attrsAdvSelect);
    $ams1->setButtonAttributes('add', array('value' =>  _("Add"),'class' =>  _("btc bt_success")));
    $ams1->setButtonAttributes('remove', array('value' => _("Remove"),'class' =>  _("btc bt_danger")));
    $ams1->setElementTemplate($eTemplate);
    echo $ams1->getElementJs(false);

    /**
     * User groups
     */
    //$userGroupList = array_diff_key($cgObj->getListContactgroup(true), $viewerGroups);
    $ams1 = $formShareView->addElement('advmultiselect', 'usergroup_id', array(_("User Group List"), _("Available"), _("Selected")), $cgObj->getListContactgroup(true), $attrsAdvSelect);
    $ams1->setButtonAttributes('add', array('value' =>  _("Add"),'class' =>  _("btc bt_success")));
    $ams1->setButtonAttributes('remove', array('value' => _("Remove"),'class' =>  _("btc bt_danger")));
    $ams1->setElementTemplate($eTemplate);
    echo $ams1->getElementJs(false);


    /**
     * Submit button
     */
    $formShareView->addElement('button', 'submit', _("Share"), array("onClick" => "submitData();", "class" => "btc bt_info"));
    $formShareView->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
    $formShareView->addElement('hidden', 'action');
    $formShareView->setDefaults(array('action' => 'share'));
    $formShareView->addElement('hidden', 'custom_view_id');
    $rendererShareView = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererShareView->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererShareView->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formShareView->accept($rendererShareView);
    $template->assign('formShareView', $rendererShareView->toArray());

    /**
     * Form add widget
     */
    $widgetObj = new CentreonWidget($centreon, $db);
    $formAddWidget = new HTML_QuickForm('formAddWidget', 'post', "?p=103");
    $formAddWidget->addElement('header', 'w_title', _('Add a widget'));
    $formAddWidget->addElement('header', 'title', _('Add a widget'));
    $formAddWidget->addElement('header', 'information', _("Widget Information"));

    /**
     * Name
     */
    $formAddWidget->addElement('text', 'widget_title', _("Widget Title"), $attrsText);

    /**
     * Widgets
     */
    $widgetList = $widgetObj->getWidgetModels();
    $widgetModels = array();
    foreach ($widgetList as $widgetModelId => $widgetModelName) {
        $widgetModels[$widgetModelId] = $widgetObj->getWidgetInfoById($widgetModelId);
    }

    /**
     * Submit button
     */
    $formAddWidget->addElement('button', 'submit', _("Submit"), array("onClick" => "submitAddWidget();","class" => "btc bt_success"));
    $formAddWidget->addElement('reset', 'reset', _("Reset"), array("class" => "btc bt_default"));
    $formAddWidget->addElement('hidden', 'action');
    $formAddWidget->addElement('hidden', 'custom_view_id');
    $formAddWidget->setDefaults(array('action' => 'addWidget'));

    /**
     * Renderer
     */
    $rendererAddWidget = new HTML_QuickForm_Renderer_ArraySmarty($template, true);
    $rendererAddWidget->setRequiredTemplate('{$label}&nbsp;<font color="red" size="1">*</font>');
    $rendererAddWidget->setErrorTemplate('<font color="red">{$error}</font><br />{$html}');
    $formAddWidget->accept($rendererAddWidget);
    $template->assign('widgetModels', $widgetModels);
    $template->assign('formAddWidget', $rendererAddWidget->toArray());
    
    $template->display("index.ihtml");
} catch (CentreonCustomViewException $e) {
    echo $e->getMessage() . "<br/>";
}
$modeEdit = 'undefined';
if (isset($_SESSION['customview_edit_mode'])) {
    $modeEdit = $_SESSION['customview_edit_mode'] == "true" ? 'true' : 'false';
}
?>
<script type="text/javascript">
var modeEdit = <?php echo $modeEdit; ?>;
/**
 * Resize widget iframe
 */
function iResize(ifrm, height)
{
	if (height < 150) {
		height = 150;
	}
	jQuery("[name="+ifrm+"]").height(height);
}
</script>
