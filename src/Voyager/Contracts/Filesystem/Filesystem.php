<?php

namespace Voyager\Contracts\Filesystem;

interface Filesystem
{
    /**
     * The public visibility setting.
     *
     * @var string
     */
    const VISIBILITY_PUBLIC = 'public';

    /**
     * The private visibility setting.
     *
     * @var string
     */
    const VISIBILITY_PRIVATE = 'private';

    /**
     * Get the full path to the file that exists at the given relative path.
     *
     * @param  string  $path
     * @return string
     */
    public function path(string $path);

    /**
     * Determine if a file exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function exists(string $path);

    /**
     * Get the contents of a file.
     *
     * @param  string  $path
     * @return string|null
     */
    public function get(string $path);

    /**
     * Get a resource to read the file.
     *
     * @param  string  $path
     * @return resource|null The path resource or null on failure.
     */
    public function readStream(string $path);

    /**
     * Write the contents of a file.
     *
     * @param  string  $path
     * @param  \Psr\Http\Message\StreamInterface|\Voyager\Http\File|\Voyager\Http\UploadedFile|string|resource  $contents
     * @param  mixed  $options
     * @return bool
     */
    public function put(string $path, mixed $contents, mixed $options = []);

    /**
     * Store the uploaded file on the disk.
     *
     * @param  \Voyager\Http\File|\Voyager\Http\UploadedFile|string  $path
     * @param  \Voyager\Http\File|\Voyager\Http\UploadedFile|string|array|null  $file
     * @param  mixed  $options
     * @return string|false
     */
    public function putFile(\Voyager\Http\File|\Voyager\Http\UploadedFile|string $path, mixed $file = null, mixed $options = []);

    /**
     * Store the uploaded file on the disk with a given name.
     *
     * @param  \Voyager\Http\File|\Voyager\Http\UploadedFile|string  $path
     * @param  \Voyager\Http\File|\Voyager\Http\UploadedFile|string|array|null  $file
     * @param  string|array|null  $name
     * @param  mixed  $options
     * @return string|false
     */
    public function putFileAs(\Voyager\Http\File|\Voyager\Http\UploadedFile|string $path, mixed $file, string|array|null $name = null, mixed $options = []);

    /**
     * Write a new file using a stream.
     *
     * @param  string  $path
     * @param  resource  $resource
     * @param  array  $options
     * @return bool
     */
    public function writeStream(string $path, mixed $resource, array $options = []);

    /**
     * Get the visibility for the given path.
     *
     * @param  string  $path
     * @return string
     */
    public function getVisibility(string $path);

    /**
     * Set the visibility for the given path.
     *
     * @param  string  $path
     * @param  string  $visibility
     * @return bool
     */
    public function setVisibility(string $path, string $visibility);

    /**
     * Prepend to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @return bool
     */
    public function prepend(string $path, string $data);

    /**
     * Append to a file.
     *
     * @param  string  $path
     * @param  string  $data
     * @return bool
     */
    public function append(string $path, string $data);

    /**
     * Delete the file at a given path.
     *
     * @param  string|array  $paths
     * @return bool
     */
    public function delete(string|array $paths);

    /**
     * Copy a file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     * @return bool
     */
    public function copy(string $from, string $to);

    /**
     * Move a file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     * @return bool
     */
    public function move(string $from, string $to);

    /**
     * Get the file size of a given file.
     *
     * @param  string  $path
     * @return int
     */
    public function size(string $path);

    /**
     * Get the file's last modification time.
     *
     * @param  string  $path
     * @return int
     */
    public function lastModified(string $path);

    /**
     * Get an array of all files in a directory.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array<string>
     */
    public function files(?string $directory = null, bool $recursive = false);

    /**
     * Get every file from the given directory (recursive).
     *
     * @param  string|null  $directory
     * @return array<string>
     */
    public function allFiles(?string $directory = null);

    /**
     * Get every directory within a given directory.
     *
     * @param  string|null  $directory
     * @param  bool  $recursive
     * @return array<string>
     */
    public function directories(?string $directory = null, bool $recursive = false);

    /**
     * Get all (recursive) of the directories within a given directory.
     *
     * @param  string|null  $directory
     * @return array<string>
     */
    public function allDirectories(?string $directory = null);

    /**
     * Create a directory.
     *
     * @param  string  $path
     * @return bool
     */
    public function makeDirectory(string $path);

    /**
     * Recursively delete a directory.
     *
     * @param  string  $directory
     * @return bool
     */
    public function deleteDirectory(string $directory);
}
