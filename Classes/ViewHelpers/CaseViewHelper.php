<?php

/**
 * Case view helper that is only usable within the SwitchViewHelper.
 * @see Tx_Fluid_ViewHelpers_SwitchViewHelper
 *
 * @api
 */
class Tx_Fluid_ViewHelpers_CaseViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper {

	/**
	 * @param mixed $value
	 * @return string the contents of this view helper if $value equals the expression of the surrounding switch view helper, otherwise an empty string
	 * @throws Tx_Fluid_Core_ViewHelper_Exception
	 * @api
	 */
	public function render($value) {
		$viewHelperVariableContainer = $this->renderingContext->getViewHelperVariableContainer();
		if (!$viewHelperVariableContainer->exists('Tx_Fluid_ViewHelper_SwitchViewHelper', 'switchExpression')) {
			throw new Tx_Fluid_Core_ViewHelper_Exception('The case View helper can only be used within a switch View helper', 1368112037);
		}
		$switchExpression = $viewHelperVariableContainer->get('Tx_Fluid_ViewHelper_SwitchViewHelper', 'switchExpression');

		// non-type-safe comparison by intention
		if ($switchExpression == $value) {
			$viewHelperVariableContainer->addOrUpdate('Tx_Fluid_ViewHelper_SwitchViewHelper', 'break', TRUE);
			return $this->renderChildren();
		}
		return '';
	}
}
?>