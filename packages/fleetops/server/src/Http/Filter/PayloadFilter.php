<?php

namespace GridX\FleetOps\Http\Filter;

use GridX\Http\Filter\Filter;

class PayloadFilter extends Filter
{
    public function queryForInternal()
    {
        $this->builder->where('company_uuid', $this->session->get('company'));
    }

    public function queryForPublic()
    {
        $this->builder->where('company_uuid', $this->session->get('company'));
    }
}
