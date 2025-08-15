<?php

namespace App\Traits;

use Illuminate\Support\Facades\Crypt;

trait Encryptable
{
    /**
     * Boot the encryptable trait for a model.
     *
     * @return void
     */
    public static function bootEncryptable()
    {
        static::saving(function ($model) {
            $model->encryptAttributes();
        });

        static::retrieved(function ($model) {
            $model->decryptAttributes();
        });
    }

    /**
     * Encrypt the encryptable attributes.
     *
     * @return void
     */
    protected function encryptAttributes()
    {
        foreach ($this->getEncryptableAttributes() as $key) {
            if (isset($this->attributes[$key]) && !$this->isEncrypted($this->attributes[$key])) {
                $this->attributes[$key] = Crypt::encryptString($this->attributes[$key]);
            }
        }
    }

    /**
     * Decrypt the encryptable attributes.
     *
     * @return void
     */
    protected function decryptAttributes()
    {
        foreach ($this->getEncryptableAttributes() as $key) {
            if (isset($this->attributes[$key]) && $this->isEncrypted($this->attributes[$key])) {
                try {
                    $this->attributes[$key] = Crypt::decryptString($this->attributes[$key]);
                } catch (\Exception $e) {
                    // Keep original value if decryption fails
                }
            }
        }
    }

    /**
     * Check if a value is encrypted.
     *
     * @param  string  $value
     * @return bool
     */
    protected function isEncrypted($value)
    {
        if (empty($value)) {
            return false;
        }
        
        try {
            Crypt::decryptString($value);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the encryptable attributes for the model.
     *
     * @return array
     */
    public function getEncryptableAttributes()
    {
        return $this->encryptable;
    }

    /**
     * Set the encryptable attributes for the model.
     *
     * @param  array  $encryptable
     * @return $this
     */
    public function setEncryptableAttributes(array $encryptable)
    {
        $this->encryptable = $encryptable;
        return $this;
    }

    /**
     * Decrypt a value manually (for queries)
     *
     * @param  string  $value
     * @return string
     */
    public static function decryptValue($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return $value;
        }
    }

    /**
     * Encrypt a value manually (for queries)
     *
     * @param  string  $value
     * @return string
     */
    public static function encryptValue($value)
    {
        return Crypt::encryptString($value);
    }
}
