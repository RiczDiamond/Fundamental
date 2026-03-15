<?php

    $prefix = DB['PREFIX'];
    $charset = trim((string) (DB['CHARSET'] ?? 'utf8mb4'));

    if ($charset === '') {

        $charset = 'utf8mb4';
    
        }

    try {
    
        $dsn = 'mysql:host=' . DB['HOST'] . ';dbname=' . DB['NAME'] . ';charset=' . $charset;

        $link = new PDO(
        
            $dsn,
            DB['USER'],
            DB['PASS'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        
        );

    } catch (PDOException $e) {
        
        error_log('Database connection failed: ' . $e->getMessage());
        http_response_code(500);
        
        exit('Database unavailable');
    
    }

    function table(string $name): string {

        global $prefix;

        return $prefix . $name;
    
    }

    function db_query(string $sql, array $params = []): bool {

        global $link;

        $stmt = $link->prepare($sql);
        
        return $stmt->execute($params);

    }

    function get_results(string $sql, array $params = []): array {

        global $link;

        $stmt = $link->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();

    }

    function get_row(string $sql, array $params = []): ?array {

        global $link;

        $stmt = $link->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ?: null;

    }

    function get_var(string $sql, array $params = []): mixed {

        global $link;

        $stmt = $link->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();

    }

    function insert(string $table, array $data): bool {

        $table = table($table);

        global $link;

        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($k)=>":$k", array_keys($data)));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $stmt = $link->prepare($sql);

        return $stmt->execute($data);

    }

    function update(string $table, array $data, array $where): bool {

        $table = table($table);

        global $link;

        $set = implode(', ', array_map(fn($k)=>"$k = :$k", array_keys($data)));
        $where_clause = implode(' AND ', array_map(fn($k)=>"$k = :where_$k", array_keys($where)));

        $params = array_merge(
            $data,
            array_combine(array_map(fn($k)=>"where_$k", array_keys($where)), array_values($where))
        );

        $sql = "UPDATE $table SET $set WHERE $where_clause";
        $stmt = $link->prepare($sql);

        return $stmt->execute($params);

    }

    function delete(string $table, array $where): bool {

        $table = table($table);

        global $link;

        $where_clause = implode(' AND ', array_map(fn($k)=>"$k = :$k", array_keys($where)));
        $sql = "DELETE FROM $table WHERE $where_clause";
        $stmt = $link->prepare($sql);

        return $stmt->execute($where);

    }

    function db_begin(): void {

        global $link;

        $link->beginTransaction();

    }

    function db_commit(): void {

        global $link;

        $link->commit();

    }

    function db_rollback(): void {

        global $link;

        $link->rollBack();
        
    }