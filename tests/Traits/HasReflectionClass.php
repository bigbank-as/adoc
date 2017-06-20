<?php

trait HasReflectionClass
{
    /**
     * @param object $object
     * @param string $propertyName
     * @return mixed
     */
    protected function getPropertyValue($object, $propertyName)
    {
        $reflection = new \ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }


    /**
     * @param object $object
     * @param string $methodName
     * @param null $_
     * @return mixed
     */
    protected function invokeMethod($object, $methodName, $_ = null)
    {
        $arguments = [];
        $parameterCount = func_num_args();
        for ($i = 2; $i < $parameterCount; $i++)
            $arguments[] = func_get_arg($i);
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $arguments);
    }
}