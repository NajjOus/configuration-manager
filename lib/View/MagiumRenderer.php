<?php
/**
 * Created by PhpStorm.
 * User: kschr
 * Date: 3/20/2017
 * Time: 4:54 PM
 */

namespace Magium\Configuration\View;

use Laminas\Form\Element;
use Laminas\Form\ElementInterface;
use Laminas\Form\View\Helper\FormElement;
use Laminas\Form\View\Helper\FormInput;
use Laminas\Form\View\Helper\FormSelect;
use Laminas\View\Helper\AbstractHelper;
use Laminas\View\Renderer\RendererInterface;

class MagiumRenderer extends AbstractHelper
{
    public function __invoke($section, $group, $id, $options)
    {
        $type = 'text';
        if (isset($options['type'])) {
            $type = $options['type'];
        }
        $value = $options['value'];

        $viewClass = 'Laminas\Form\View\Helper\Form' . ucfirst(strtolower($type));
        $formClass = 'Laminas\Form\Element\\' . ucfirst(strtolower($type));
        $reflectionClass = new \ReflectionClass($viewClass);
        if (!$reflectionClass->isSubclassOf(AbstractHelper::class)) {
            throw new InvalidViewConfigurationException('Invalid setting input type');
        }

        $instance = $reflectionClass->newInstance();
        $formInstance = new \ReflectionClass($formClass);
        $this->configureFormView($instance);
        $name = sprintf('%s_%s_%s', $section, $group, $id);
        $formElement = $formInstance->newInstance($name, $options);
        /* @var $formElement Element */
        if ($formElement instanceof Element\Select) {
            $formElement->setOptions(['options' => $options['source']]);
        }
        $formElement->setAttribute('onchange', 'magiumRegisterChange(event)');
        $formElement->setAttribute('class', 'form-control');
        $formElement->setAttribute('data-path', $options['path']);
        $formElement->setValue($value);
        $output = $instance->render($formElement);
        return $output;
    }

    protected function configureFormView(AbstractHelper $instance)
    {
        $view = $this->getView();
        if (!$view instanceof RendererInterface) {
            throw new InvalidViewConfigurationException('View is not the correct instance type');
        }
        $instance->setView($view);
    }

}
