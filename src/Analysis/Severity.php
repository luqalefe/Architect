<?php

declare(strict_types=1);

namespace LaravelModulesArch\Analysis;

enum Severity: string
{
    case Error = 'error';
    case Warning = 'warning';
}
