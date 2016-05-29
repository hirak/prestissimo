<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

register_shutdown_function(function () {
    $fp = fopen('tags', 'wb');

    $build = function (array $arr) {
        $b = array();
        foreach ($arr as $key => $val) {
            if ($val) {
                $b[] = "$key:$val";
            }
        }
        return implode("\t", $b);
    };

    $base = dirname(__DIR__) . '/';
    $it = new AppendIterator;
    $it->append(new ArrayIterator(get_declared_classes()));
    $it->append(new ArrayIterator(get_declared_interfaces()));
    if (function_exists('get_declared_traits')) {
        $it->append(new ArrayIterator(get_declared_traits()));
    }

    foreach ($it as $class) {
        $pathes = explode('\\', $class);
        if ('Test' === substr($class, -4)
            || 'Spec' === substr($class, -4)
            || 'PHP_Token_' === substr($class, 0, 10)
            || 'Double' === $pathes[0]
            || in_array('Test', $pathes)
            || in_array('Spec', $pathes)
        ) {
            continue;
        }
        $rc = new ReflectionClass($class);
        if ($rc->isInternal()) {
            continue;
        }
        if ($rc->isInterface()) {
            $kind = 'i';
        } elseif (method_exists($rc, 'isTrait')) {
            if ($rc->isTrait()) {
                $kind = 't';
            } else {
                $kind = 'c';
            }
        } else {
            $kind = 'c';
        }
        $info = array(
            'kind' => $kind,
            'namespace' => $rc->getNamespaceName(),
        );
        $parent = $rc->getParentClass();
        if ($parent) {
            $info['inherits'] = $parent->getName();
        }
        $construct = $rc->getConstructor();
        if ($construct) {
            $params = array();
            foreach ($construct->getParameters() as $rp) {
                $class = $rp->getClass();
                $param = '';
                if ($class) {
                    $param .= $class->getName() . ' ';
                } elseif ($rp->isArray()) {
                    $param .= 'array ';
                }
                $param .= '$' . $rp->getName();
                $params[] = $param;
            }
            $info['constructor'] = '(' . implode(', ', $params) . ')';
        }
        if ($rc->isInternal()) {
            $filename = '(ext-' . $rc->getExtensionName() . ')';
        } else {
            $filename = str_replace($base, '', $rc->getFileName());
        }
        fwrite($fp, implode("\t", array(
            $rc->getShortName(),
            $filename,
            $rc->getStartLine() . ';"',
            '',
        )) . $build($info) . PHP_EOL);

        // constants
        foreach ($rc->getConstants() as $name => $value) {
            $info = array(
                'kind' => 'd',
                'class' => $rc->getShortName(),
                'namespace' => $rc->getNamespaceName(),
            );
            fwrite($fp, implode("\t", array(
                $name,
                $filename,
                $rc->getStartLine() . ';"',
                '',
            )) . $build($info) . PHP_EOL);
        }

        // properties
        foreach ($rc->getProperties(ReflectionProperty::IS_PUBLIC) as $rp) {
            $info = array(
                'kind' => 'p',
                'class' => $rc->getShortName(),
                'namespace' => $rc->getNamespaceName(),
                'modifiers' => implode(' ', Reflection::getModifierNames($rp->getModifiers())),
            );
            fwrite($fp, implode("\t", array(
                $rp->getName(),
                $filename,
                $rc->getStartLine() . ';"',
                '',
            )) . $build($info) . PHP_EOL);
        }

        // methods
        foreach ($rc->getMethods(ReflectionMethod::IS_PUBLIC|ReflectionMethod::IS_PROTECTED) as $rm) {
            if ('__' === substr($rm->getName(), 0, 2)) {
                continue;
            }
            if (false === $rm->getStartLine()) {
                continue;
            }
            $info = array(
                'kind' => 'm',
                'class' => $rc->getShortName(),
                'namespace' => $rc->getNamespaceName(),
                'modifiers' => implode(' ', Reflection::getModifierNames($rm->getModifiers())),
                //'prototype' => .
            );
            $params = array();
            foreach ($rm->getParameters() as $rp) {
                $class = '';
                if (PHP_VERSION > 50300) {
                    $class = $rp->getClass();
                }
                $param = '';
                if ($class) {
                    $param = $class->getName() . ' ';
                } elseif ($rp->isArray()) {
                    $param = 'array ';
                }
                $param .= '$' . $rp->getName();
                if ($rp->isOptional() && $rm->isUserDefined()) {
                    $param .= '=' . json_encode($rp->getDefaultValue());
                }
                $params[] = $param;
            }
            $info['type'] = '(' . implode(', ', $params) . ')';
            fwrite($fp, implode("\t", array(
                $rm->getName(),
                $filename,
                $rm->getStartLine() . ';"',
                '',
            )) . $build($info) . PHP_EOL);
        }
    }
    unset($it);

    // functions
    $funcs = get_defined_functions();
    foreach ($funcs['internal'] as $func) {
        $rf = new ReflectionFunction($func);
        $info = array(
            'kind' => 'f',
            'namespace' => $rf->getNamespaceName(),
            //'prototype' => .
        );
        $params = array();
        foreach ($rf->getParameters() as $rp) {
            $class = '';
            if (!defined('HHVM_VERSION')) {
                $class = $rp->getClass();
            }
            $param = '';
            if ($class) {
                $param = $class->getName() . ' ';
            } elseif ($rp->isArray()) {
                $param = 'array ';
            }
            $param .= '$' . $rp->getName();
            if ($rp->isOptional() && $rf->isUserDefined()) {
                $param .= '=' . json_encode($rp->getDefaultValue());
            }
            $params[] = $param;
        }
        $info['type'] = '(' . implode(', ', $params) . ')';
        if ($rf->isInternal()) {
            $filename = '(ext-' . $rf->getExtensionName() . ')';
        } else {
            $filename = str_replace($base, '', $rf->getFileName());
        }
        fwrite($fp, implode("\t", array(
            $rf->getName(),
            $filename,
            $rf->getStartLine() . ';"',
            '',
        )) . $build($info) . PHP_EOL);
    }
});
