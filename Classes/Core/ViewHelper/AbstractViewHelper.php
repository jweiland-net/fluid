<?php

/**
 * The abstract base class for all view helpers.
 *
 * @api
 */
abstract class Tx_Fluid_Core_ViewHelper_AbstractViewHelper {

	/**
	 * TRUE if arguments have already been initialized
	 *
	 * @var boolean
	 */
	private $argumentsInitialized = FALSE;

	/**
	 * Stores all Tx_Fluid_Core_ViewHelper_ArgumentDefinition instances
	 *
	 * @var array
	 */
	private $argumentDefinitions = array();

	/**
	 * Cache of argument definitions; the key is the ViewHelper class name, and the
	 * value is the array of argument definitions.
	 *
	 * In our benchmarks, this cache leads to a 40% improvement when using a certain
	 * ViewHelper class many times throughout the rendering process.
	 *
	 * @var array
	 */
	static private $argumentDefinitionCache = array();

	/**
	 * Current view helper node
	 *
	 * @var Tx_Fluid_Core_Parser_SyntaxTree_ViewHelperNode
	 */
	private $viewHelperNode;

	/**
	 * Arguments array.
	 *
	 * @var array
	 * @api
	 */
	protected $arguments;

	/**
	 * Current variable container reference.
	 *
	 * @var Tx_Fluid_Core_ViewHelper_TemplateVariableContainer
	 * @api
	 */
	protected $templateVariableContainer;

	/**
	 * Controller Context to use
	 *
	 * @var Tx_Extbase_MVC_Controller_ControllerContext
	 * @api
	 */
	protected $controllerContext;

	/**
	 * @var Tx_Fluid_Core_Rendering_RenderingContextInterface
	 */
	protected $renderingContext;

	/**
	 * @var Closure
	 */
	protected $renderChildrenClosure = NULL;

	/**
	 * ViewHelper Variable Container
	 *
	 * @var Tx_Fluid_Core_ViewHelper_ViewHelperVariableContainer
	 * @api
	 */
	protected $viewHelperVariableContainer;

	/**
	 * Reflection service
	 *
	 * @var Tx_Extbase_Reflection_Service
	 */
	private $reflectionService;

	/**
	 * @var Tx_Extbase_Object_ObjectManagerInterface
	 */
	protected $objectManager;

	/**
	 * With this flag, you can disable the escaping interceptor inside this ViewHelper.
	 * THIS MIGHT CHANGE WITHOUT NOTICE, NO PUBLIC API!
	 * @var boolean
	 */
	protected $escapingInterceptorEnabled = TRUE;

	/**
	 * @param Tx_Extbase_Object_ObjectManagerInterface $objectManager
	 * @return void
	 */
	public function injectObjectManager(Tx_Extbase_Object_ObjectManagerInterface $objectManager) {
		$this->objectManager = $objectManager;
	}

	/**
	 * @param array $arguments
	 * @return void
	 */
	public function setArguments(array $arguments) {
		$this->arguments = $arguments;
	}

	/**
	 * @param Tx_Fluid_Core_Rendering_RenderingContextInterface $renderingContext
	 * @return void
	 */
	public function setRenderingContext(Tx_Fluid_Core_Rendering_RenderingContextInterface $renderingContext) {
		$this->renderingContext = $renderingContext;
		$this->templateVariableContainer = $renderingContext->getTemplateVariableContainer();
		if ($renderingContext->getControllerContext() !== NULL) {
			$this->controllerContext = $renderingContext->getControllerContext();
		}
		$this->viewHelperVariableContainer = $renderingContext->getViewHelperVariableContainer();
	}

	/**
	 * Inject a Reflection service
	 *
	 * @param Tx_Extbase_Reflection_Service $reflectionService Reflection service
	 */
	public function injectReflectionService(Tx_Extbase_Reflection_Service $reflectionService) {
		$this->reflectionService = $reflectionService;
	}

	/**
	 * Returns whether the escaping interceptor should be disabled or enabled inside the tags contents.
	 *
	 * THIS METHOD MIGHT CHANGE WITHOUT NOTICE; NO PUBLIC API!
	 *
	 * @return boolean
	 */
	public function isEscapingInterceptorEnabled() {
		return $this->escapingInterceptorEnabled;
	}

	/**
	 * Register a new argument. Call this method from your ViewHelper subclass
	 * inside the initializeArguments() method.
	 *
	 * @param string $name Name of the argument
	 * @param string $type Type of the argument
	 * @param string $description Description of the argument
	 * @param boolean $required If TRUE, argument is required. Defaults to FALSE.
	 * @param mixed $defaultValue Default value of argument
	 * @return Tx_Fluid_Core_ViewHelper_AbstractViewHelper $this, to allow chaining.
	 * @throws Tx_Fluid_Core_ViewHelper_Exception
	 * @api
	 */
	protected function registerArgument($name, $type, $description, $required = FALSE, $defaultValue = NULL) {
		if (array_key_exists($name, $this->argumentDefinitions)) {
			throw new Tx_Fluid_Core_ViewHelper_Exception('Argument "' . $name . '" has already been defined, thus it should not be defined again.', 1253036401);
		}
		$this->argumentDefinitions[$name] = new Tx_Fluid_Core_ViewHelper_ArgumentDefinition($name, $type, $description, $required, $defaultValue);
		return $this;
	}

	/**
	 * Overrides a registered argument. Call this method from your ViewHelper subclass
	 * inside the initializeArguments() method if you want to override a previously registered argument.
	 *
	 * @see registerArgument()
	 * @param string $name Name of the argument
	 * @param string $type Type of the argument
	 * @param string $description Description of the argument
	 * @param boolean $required If TRUE, argument is required. Defaults to FALSE.
	 * @param mixed $defaultValue Default value of argument
	 * @return Tx_Fluid_Core_ViewHelper_AbstractViewHelper $this, to allow chaining.
	 * @throws Tx_Fluid_Core_ViewHelper_Exception
	 * @api
	 */
	protected function overrideArgument($name, $type, $description, $required = FALSE, $defaultValue = NULL) {
		if (!array_key_exists($name, $this->argumentDefinitions)) {
			throw new Tx_Fluid_Core_ViewHelper_Exception('Argument "' . $name . '" has not been defined, thus it can\'t be overridden.', 1279212461);
		}
		$this->argumentDefinitions[$name] = new Tx_Fluid_Core_ViewHelper_ArgumentDefinition($name, $type, $description, $required, $defaultValue);
		return $this;
	}

	/**
	 * Sets all needed attributes needed for the rendering. Called by the
	 * framework. Populates $this->viewHelperNode.
	 * This is PURELY INTERNAL! Never override this method!!
	 *
	 * @param Tx_Fluid_Core_Parser_SyntaxTree_ViewHelperNode $node View Helper node to be set.
	 * @return void
	 */
	public function setViewHelperNode(Tx_Fluid_Core_Parser_SyntaxTree_ViewHelperNode $node) {
		$this->viewHelperNode = $node;
	}

	/**
	 * Called when being inside a cached template.
	 *
	 * @param Closure $renderChildrenClosure
	 * @return void
	 */
	public function setRenderChildrenClosure(Closure $renderChildrenClosure) {
		$this->renderChildrenClosure = $renderChildrenClosure;
	}

	/**
	 * Initialize the arguments of the ViewHelper, and call the render() method of the ViewHelper.
	 *
	 * @return string the rendered ViewHelper.
	 */
	public function initializeArgumentsAndRender() {
		$this->validateArguments();
		$this->initialize();

		return $this->callRenderMethod();
	}

	/**
	 * Call the render() method and handle errors.
	 *
	 * @return string the rendered ViewHelper
	 * @throws Tx_Fluid_Core_ViewHelper_Exception
	 */
	protected function callRenderMethod() {
		$renderMethodParameters = array();
		foreach ($this->argumentDefinitions as $argumentName => $argumentDefinition) {
			if ($argumentDefinition->isMethodParameter()) {
				$renderMethodParameters[$argumentName] = $this->arguments[$argumentName];
			}
		}

		try {
			return call_user_func_array(array($this, 'render'), $renderMethodParameters);
		} catch (Tx_Fluid_Core_ViewHelper_Exception $exception) {
			// @todo [BW] rethrow exception, log, ignore.. depending on the current context
			return $exception->getMessage();
		}
	}

	/**
	 * Initializes the view helper before invoking the render method.
	 *
	 * Override this method to solve tasks before the view helper content is rendered.
	 *
	 * @return void
	 * @api
	 */
	public function initialize() {
	}

	/**
	 * Helper method which triggers the rendering of everything between the
	 * opening and the closing tag.
	 *
	 * @return mixed The finally rendered child nodes.
	 * @api
	 */
	public function renderChildren() {
		if ($this->renderChildrenClosure !== NULL) {
			$closure = $this->renderChildrenClosure;
			return $closure();
		}
		return $this->viewHelperNode->evaluateChildNodes($this->renderingContext);
	}

	/**
	 * Helper which is mostly needed when calling renderStatic() from within
	 * render().
	 *
	 * No public API yet.
	 *
	 * @return Closure
	 */
	protected function buildRenderChildrenClosure() {
		$self = $this;
		return function () use ($self) {
			return $self->renderChildren();
		};
	}

	/**
	 * Initialize all arguments and return them
	 *
	 * @return array Array of Tx_Fluid_Core_ViewHelper_ArgumentDefinition instances.
	 */
	public function prepareArguments() {
		if (!$this->argumentsInitialized) {
			$thisClassName = get_class($this);
			if (isset(self::$argumentDefinitionCache[$thisClassName])) {
				$this->argumentDefinitions = self::$argumentDefinitionCache[$thisClassName];
			} else {
				$this->registerRenderMethodArguments();
				$this->initializeArguments();
				self::$argumentDefinitionCache[$thisClassName] = $this->argumentDefinitions;
			}
			$this->argumentsInitialized = TRUE;
		}
		return $this->argumentDefinitions;
	}

	/**
	 * Register method arguments for "render" by analysing the doc comment above.
	 *
	 * @return void
	 * @throws Tx_Fluid_Core_Parser_Exception
	 */
	private function registerRenderMethodArguments() {
		$methodParameters = $this->reflectionService->getMethodParameters(get_class($this), 'render');
		if (count($methodParameters) === 0) {
			return;
		}

		if (Tx_Fluid_Fluid::$debugMode) {
			$methodTags = $this->reflectionService->getMethodTagsValues(get_class($this), 'render');

			$paramAnnotations = array();
			if (isset($methodTags['param'])) {
				$paramAnnotations = $methodTags['param'];
			}
		}

		$i = 0;
		foreach ($methodParameters as $parameterName => $parameterInfo) {
			$dataType = NULL;
			if (isset($parameterInfo['type'])) {
				$dataType = $parameterInfo['type'];
			} elseif ($parameterInfo['array']) {
				$dataType = 'array';
			}
			if ($dataType === NULL) {
				throw new Tx_Fluid_Core_Parser_Exception('could not determine type of argument "' . $parameterName . '" of the render-method in ViewHelper "' . get_class($this) . '". Either the methods docComment is invalid or some PHP optimizer strips off comments.', 1242292003);
			}

			$description = '';
			if (Tx_Fluid_Fluid::$debugMode && isset($paramAnnotations[$i])) {
				$explodedAnnotation = explode(' ', $paramAnnotations[$i]);
				array_shift($explodedAnnotation);
				array_shift($explodedAnnotation);
				$description = implode(' ', $explodedAnnotation);
			}
			$defaultValue = NULL;
			if (isset($parameterInfo['defaultValue'])) {
				$defaultValue = $parameterInfo['defaultValue'];
			}
			$this->argumentDefinitions[$parameterName] = new Tx_Fluid_Core_ViewHelper_ArgumentDefinition($parameterName, $dataType, $description, ($parameterInfo['optional'] === FALSE), $defaultValue, TRUE);
			$i++;
		}
	}

	/**
	 * Validate arguments, and throw exception if arguments do not validate.
	 *
	 * @return void
	 * @throws InvalidArgumentException
	 */
	public function validateArguments() {
		$argumentDefinitions = $this->prepareArguments();
		if (!count($argumentDefinitions)) {
			return;
		}
		foreach ($argumentDefinitions as $argumentName => $registeredArgument) {
			if ($this->hasArgument($argumentName)) {
				$type = $registeredArgument->getType();
				if ($this->arguments[$argumentName] === $registeredArgument->getDefaultValue()) {
					continue;
				}
				if ($type === 'array') {
					if (!is_array($this->arguments[$argumentName]) && !$this->arguments[$argumentName] instanceof ArrayAccess && !$this->arguments[$argumentName] instanceof Traversable) {
						throw new InvalidArgumentException('The argument "' . $argumentName . '" was registered with type "array", but is of type "' . gettype($this->arguments[$argumentName]) . '" in view helper "' . get_class($this) . '"', 1237900529);
					}
				} elseif ($type === 'boolean') {
					if (!is_bool($this->arguments[$argumentName])) {
						throw new InvalidArgumentException('The argument "' . $argumentName . '" was registered with type "boolean", but is of type "' . gettype($this->arguments[$argumentName]) . '" in view helper "' . get_class($this) . '".', 1240227732);
					}
				} elseif (class_exists($type, FALSE)) {
					if (!($this->arguments[$argumentName] instanceof $type)) {
						if (is_object($this->arguments[$argumentName])) {
							throw new InvalidArgumentException('The argument "' . $argumentName . '" was registered with type "' . $type . '", but is of type "' . get_class($this->arguments[$argumentName]) . '" in view helper "' . get_class($this) . '".', 1256475114);
						} else {
							throw new InvalidArgumentException('The argument "' . $argumentName . '" was registered with type "' . $type . '", but is of type "' . gettype($this->arguments[$argumentName]) . '" in view helper "' . get_class($this) . '".', 1256475113);
						}
					}
				}
			}
		}
	}

	/**
	 * Initialize all arguments. You need to override this method and call
	 * $this->registerArgument(...) inside this method, to register all your arguments.
	 *
	 * @return void
	 * @api
	 */
	public function initializeArguments() {
	}

	/**
	 * Render method you need to implement for your custom view helper.
	 * Available objects at this point are $this->arguments, and $this->templateVariableContainer.
	 *
	 * Besides, you often need $this->renderChildren().
	 *
	 * @return string rendered string, view helper specific
	 * @api
	 */
	//abstract public function render();

	/**
	 * Tests if the given $argumentName is set, and not NULL.
	 *
	 * @param string $argumentName
	 * @return boolean TRUE if $argumentName is found, FALSE otherwise
	 * @api
	 */
	protected function hasArgument($argumentName) {
		return isset($this->arguments[$argumentName]) && $this->arguments[$argumentName] !== NULL;
	}

	/**
	 * Default implementation for CompilableInterface. By default,
	 * inserts a renderStatic() call to itself.
	 *
	 * You only should override this method *when you absolutely know what you
	 * are doing*, and really want to influence the generated PHP code during
	 * template compilation directly.
	 *
	 * @param string $argumentsVariableName
	 * @param string $renderChildrenClosureVariableName
	 * @param string $initializationPhpCode
	 * @param Tx_Fluid_Core_Parser_SyntaxTree_AbstractNode $syntaxTreeNode
	 * @param Tx_Fluid_Core_Compiler_TemplateCompiler $templateCompiler
	 * @return string
	 * @see Tx_Fluid_Core_ViewHelper_Facets_CompilableInterface
	 */
	public function compile($argumentsVariableName, $renderChildrenClosureVariableName, &$initializationPhpCode, Tx_Fluid_Core_Parser_SyntaxTree_AbstractNode $syntaxTreeNode, Tx_Fluid_Core_Compiler_TemplateCompiler $templateCompiler) {
		return sprintf('%s::renderStatic(%s, %s, $renderingContext)',
			get_class($this), $argumentsVariableName, $renderChildrenClosureVariableName);
	}

	/**
	 * Default implementation for CompilableInterface. See CompilableInterface
	 * for a detailed description of this method.
	 *
	 * @param array $arguments
	 * @param Closure $renderChildrenClosure
	 * @param Tx_Fluid_Core_Rendering_RenderingContextInterface $renderingContext
	 * @return mixed
	 * @see Tx_Fluid_Core_ViewHelper_Facets_CompilableInterface
	 */
	static public function renderStatic(array $arguments, Closure $renderChildrenClosure, Tx_Fluid_Core_Rendering_RenderingContextInterface $renderingContext) {
		return NULL;
	}

	/**
	 * Resets the ViewHelper state.
	 *
	 * Overwrite this method if you need to get a clean state of your ViewHelper.
	 *
	 * @return void
	 */
	public function resetState() {
	}
}

?>