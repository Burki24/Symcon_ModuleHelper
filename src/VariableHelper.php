<?php

declare(strict_types=1);

namespace Burki24\SymconModuleHelper;

/**
 * Provides reusable access helpers for variables below a Symcon module instance.
 *
 * Intended for classes derived from IPSModule/IPSModuleStrict. The helper
 * resolves variables by ident below the current instance and normalizes a
 * missing lookup to ID 0.
 *
 * @version 1.0.0
 */
trait VariableHelper
{
    /**
     * Returns the variable ID for an ident below the current module instance.
     *
     * @param string $ident Ident of the variable below the current instance.
     *
     * @return int Variable ID, or 0 if no matching object exists.
     */
    protected function GetVariableIDByIdent(string $ident): int
    {
        $variableID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

        return is_int($variableID) && $variableID > 0 ? $variableID : 0;
    }

    /**
     * Checks whether a variable with the given ident exists below the current module instance.
     *
     * @param string $ident Ident of the variable below the current instance.
     *
     * @return bool True if a matching variable exists.
     */
    protected function VariableExists(string $ident): bool
    {
        return $this->GetVariableIDByIdent($ident) > 0;
    }
}
