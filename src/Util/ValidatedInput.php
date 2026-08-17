<?php

namespace getoma\dbfe\Util;

final class ValidatedInput
{
   public function __construct(
      private array $data
   )
   {
   }

   public function get(string $key): mixed
   {
      return $this->data[$key] ?? null;
   }

   public function get_scalar(string $key): mixed
   {
      if( !isset($this->data[$key]) ) return null;
      if( is_scalar($this->data[$key]) ) return $this->data[$key];
      throw new \RuntimeException("key $key is not a scalar value");
   }

   public function get_array(string $key): array
   {
      if( !isset($this->data[$key]) ) return [];
      if( is_array($this->data[$key]) ) return $this->data[$key];
      throw new \RuntimeException("key $key is not an array");
   }

   public function store(string $key, mixed $value): void
   {
      $this->data[$key] = $value;
   }

   public function push(string $key, mixed $value): void
   {
      $this->data[$key] ??= [];
      if(!is_array($this->data[$key])) throw new \RuntimeException("key $key is not an array");
      $this->data[$key][] = $value;
   }

   public function has(string $key): bool
   {
      return isset($this->data[$key]);
   }

   public function hasValue(string $key): bool
   {
      return isset($this->data[$key]) && !empty($this->data[$key]);
   }

   public function is_array(string $key): bool
   {
      return is_array($this->data[$key]??null);
   }
}