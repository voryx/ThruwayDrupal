<?php

/**
 * @file
 * Contains \Drupal\thruway\Encoder\ArrayEncoder.
 */

namespace Drupal\thruway\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

/**
 * Adds Array support for serializer.
 */
class ArrayEncoder implements EncoderInterface, DecoderInterface {

  /**
   * The formats that this Encoder supports.
   *
   * @var array
   */
  static protected $format = array('array');


  /**
   * Implements \Symfony\Component\Serializer\Encoder\EncoderInterface::encode().
   */
  public function encode($data, $format, array $context = array()) {
    return $data;
  }

  /**
   * Implements \Symfony\Component\Serializer\Encoder\JsonEncoder::supportsEncoding().
   */
  public function supportsEncoding($format) {
    return in_array($format, static::$format);
  }

  /**
   * Implements \Symfony\Component\Serializer\Encoder\EncoderInterface::decode().
   */
  public function decode($data, $format, array $context = array()) {
    return $data;
  }

  /**
   * Implements \Symfony\Component\Serializer\Encoder\JsonEncoder::supportsDecoding().
   */
  public function supportsDecoding($format) {
    return in_array($format, static::$format);
  }
}
