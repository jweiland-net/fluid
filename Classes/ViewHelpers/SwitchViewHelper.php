<?php

/**
 * Switch view helper which can be used to render content depending on a value or expression.
 * Implements what a basic switch()-PHP-method does.
 *
 * = Examples =
 *
 * <code title="Simple Switch statement">
 * <f:switch expression="{person.gender}">
 *   <f:case value="male">Mr.</f:case>
 *   <f:case value="female">Mrs.</f:case>
 * </f:switch>
 * </code>
 * <output>
 * Mr. / Mrs. (depending on the value of {person.gender})
 * </output>
 *
 * Note: Using this view helper can be a sign of weak architecture. If you end up using it extensively
 * you might want to consider restructuring your controllers/actions and/or use partials and sections.
 * E.g. the above example could be achieved with <f:render partial="title.{person.gender}" /> and the partials
 * "title.male.html", "title.female.html", ...
 * Depending on the scenario this can be easier to extend and possibly contains less duplication.
 *
 * @api
 */
class Tx_Fluid_ViewHelpers_SwitchViewHelper extends Tx_Fluid_Core_ViewHelper_AbstractViewHelper implements Tx_Fluid_Core_ViewHelper_Facets_ChildNodeAccessInterface {

	/**
	 * An array of Tx_Fluid_Core_Parser_SyntaxTree_AbstractNode
	 * @var array
	 */
	private $childNodes = array();

	/**
	 * @var mixed
	 */
	protected $backupSwitchExpression = NULL;

	/**
	 * @var boolean
	 */
	protected $backupBreakState = FALSE;

	/**
	 * Setter for ChildNodes - as defined in ChildNodeAccessInterface
	 *
	 * @param array $childNodes Child nodes of this syntax tree node
	 * @return void
	 */
	public function setChildNodes(array $childNodes) {
		$this->childNodes = $childNodes;
	}

	/**
	 * @param mixed $expression
	 * @return string the rendered string
	 * @api
	 */
	public function render($expression) {
		$content = '';
		$this->backupSwitchState();
		$templateVariableContainer = $this->renderingContext->getViewHelperVariableContainer();

		$templateVariableContainer->addOrUpdate('Tx_Fluid_ViewHelper_SwitchViewHelper', 'switchExpression', $expression);
		$templateVariableContainer->addOrUpdate('Tx_Fluid_ViewHelper_SwitchViewHelper', 'break', FALSE);

		foreach ($this->childNodes as $childNode) {
			if (
				!$childNode instanceof Tx_Fluid_Core_Parser_SyntaxTree_ViewHelperNode
				|| $childNode->getViewHelperClassName() !== 'Tx_Fluid_ViewHelpers_CaseViewHelper'
			) {
				continue;
			}
			$content = $childNode->evaluate($this->renderingContext);
			if ($templateVariableContainer->get('Tx_Fluid_ViewHelper_SwitchViewHelper', 'break') === TRUE) {
				break;
			}
		}

		$templateVariableContainer->remove('Tx_Fluid_ViewHelper_SwitchViewHelper', 'switchExpression');
		$templateVariableContainer->remove('Tx_Fluid_ViewHelper_SwitchViewHelper', 'break');

		$this->restoreSwitchState();
		return $content;
	}

	/**
	 * Backups "switch expression" and "break" state of a possible parent switch ViewHelper to support nesting
	 *
	 * @return void
	 */
	protected function backupSwitchState() {
		if ($this->renderingContext->getViewHelperVariableContainer()->exists('Tx_Fluid_ViewHelper_SwitchViewHelper', 'switchExpression')) {
			$this->backupSwitchExpression = $this->renderingContext->getViewHelperVariableContainer()->get('Tx_Fluid_ViewHelper_SwitchViewHelper', 'switchExpression');
		}
		if ($this->renderingContext->getViewHelperVariableContainer()->exists('Tx_Fluid_ViewHelper_SwitchViewHelper', 'break')) {
			$this->backupBreakState = $this->renderingContext->getViewHelperVariableContainer()->get('Tx_Fluid_ViewHelper_SwitchViewHelper', 'break');
		}
	}

	/**
	 * Restores "switch expression" and "break" states that might have been backed up in backupSwitchState() before
	 *
	 * @return void
	 */
	protected function restoreSwitchState() {
		if ($this->backupSwitchExpression !== NULL) {
			$this->renderingContext->getViewHelperVariableContainer()->addOrUpdate(
				'Tx_Fluid_ViewHelper_SwitchViewHelper',
				'switchExpression',
				$this->backupSwitchExpression
			);
		}
		if ($this->backupBreakState !== FALSE) {
			$this->renderingContext->getViewHelperVariableContainer()->addOrUpdate('Tx_Fluid_ViewHelper_SwitchViewHelper', 'break', TRUE);
		}
	}
}
?>