<?php

namespace App\Exceptions;

use App\Models\FavouriteContact;
use RuntimeException;

class PotentialDuplicateFavouriteException extends RuntimeException
{
    public function __construct(public readonly FavouriteContact $contact)
    {
        parent::__construct('Kemungkinan Favourite pendua ditemui. Sahkan penggunaan rekod sedia ada sebelum menyimpan transaksi.');
    }
}
