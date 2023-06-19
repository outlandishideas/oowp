<?php

namespace Outlandish\Wordpress\Oowp\PostTypes\Traits;

trait HasAcfMetadata
{
    /**
     * Gets the metadata (custom fields) for the post
     * @param string $name
     * @param bool $single
     * @return array|string
     */
    public function metadata(string $name, bool $single = true) : mixed
    {
        return $this->getAcf($name, $single);
    }

    /**
     * Sets the metadata with the given key for the post
     *
     * @param string $key
     * @param mixed $value
     */
    public function setMetadata(string $key, mixed $value) : int|bool
    {
        return $this->setAcf($key, $value);
    }

    public function deleteMetadata(string $key) : bool
    {
        return $this->deleteAcf($key);
    }

    protected function featuredImageAttachmentId() : int|bool
    {
        $image = $this->metadata('featured_image', true) ?: $this->metadata('image', true);

        if ($image) {
            if (is_numeric($image)) {
                return $image;
            }
            return $image['id'];
        }

        return get_post_thumbnail_id($this->getWpPost());
    }

    public function getAcf(string $name, bool $single) : mixed
    {
        $meta = null;
        if (function_exists('get_field')) {
            $field = get_field_object($name, $this->ID);
            // if not found by acf, then may not be an acf-configured field, so fall back on normal wp method
            if ($field === false || !$field['key']) {
                $meta = get_post_meta($this->ID, $name, $single);
            } else {
                $meta = $field['value'];
            }
        }
        if (!$single && !$meta) {
            $meta = []; // ensure return type is an array
        }
        return $meta;
    }

    /**
     * Sets the metadata with the given key for the post
     *
     * @param string $key
     * @param mixed $value
     */
    public function setAcf(string $key, mixed $value) : int|bool
    {
        if (function_exists('update_field')) {
            return update_field($key, $value, $this->ID);
        }
        return false;
    }

    public function deleteAcf(string $key) : bool
    {
        if (function_exists('delete_field')) {
            return delete_field($key, $this->ID);
        }
        return false;
    }
}
