<?php

namespace GridX\Pallet\Http\Filter;

use GridX\Http\Filter\Filter;

class SupplierFilter extends Filter
{
    public function queryForInternal()
    {
        $this->builder->where('company_uuid', $this->session->get('company'));
        $this->builder->where('type', 'pallet-supplier');
    }

    public function query(?string $query)
    {
        $this->builder->search($query);
        $this->builder->where('type', 'pallet-supplier');
    }
}
