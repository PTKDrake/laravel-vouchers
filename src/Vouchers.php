<?php

namespace BeyondCode\Vouchers;

use BeyondCode\Vouchers\Exceptions\VoucherAlreadyMaxUsed;
use BeyondCode\Vouchers\Exceptions\VoucherExpired;
use BeyondCode\Vouchers\Exceptions\VoucherIsInvalid;
use BeyondCode\Vouchers\Models\Voucher;
use Illuminate\Database\Eloquent\Model;

class Vouchers
{
    /** @var VoucherGenerator */
    private $generator;
    /** @var Voucher */
    private $voucherModel;

    public function __construct(VoucherGenerator $generator)
    {
        $this->generator = $generator;
        $this->voucherModel = app(config('vouchers.model', Voucher::class));
    }

    /**
     * Generate the specified amount of codes and return
     * an array with all the generated codes.
     *
     * @param int $amount
     * @return array
     */
    public function generate(int $amount = 1): array
    {
        $codes = [];

        for ($i = 1; $i <= $amount; $i++) {
            $codes[] = $this->getUniqueVoucher();
        }

        return $codes;
    }

    /**
     * @param Model $model
     * @param int $amount
     * @param array $data
     * @param null $expires_at
     * @param int $use_count
     * @return array
     */
    public function create(Model $model, int $amount = 1, array $data = [], $expires_at = null, int $use_count = 1): array
    {
        $vouchers = [];

        foreach ($this->generate($amount) as $voucherCode) {
            $vouchers[] = $this->voucherModel->create([
                'model_id' => $model->getKey(),
                'model_type' => $model->getMorphClass(),
                'code' => $voucherCode,
                'data' => $data,
                'expires_at' => $expires_at,
                'use_count' => $use_count,
                'used_count' => 0
            ]);
        }

        return $vouchers;
    }

    /**
     * @param string $code
     * @return Voucher
     * @throws VoucherExpired
     * *@throws VoucherAlreadyMaxUsed
     * @throws VoucherIsInvalid
     */
    public function check(string $code)
    {
        /** @var Voucher $voucher */
        $voucher = $this->voucherModel->whereCode($code)->first();

        if (is_null($voucher)) {
            throw VoucherIsInvalid::withCode($code);
        }
        if ($voucher->isExpired()) {
            throw VoucherExpired::create($voucher);
        }
        if($voucher->isMaxUsed()){
            throw VoucherAlreadyMaxUsed::create($voucher);
        }

        return $voucher;
    }

    /**
     * @return string
     */
    protected function getUniqueVoucher(): string
    {
        $voucher = $this->generator->generateUnique();

        while ($this->voucherModel->whereCode($voucher)->count() > 0) {
            $voucher = $this->generator->generateUnique();
        }

        return $voucher;
    }
}
