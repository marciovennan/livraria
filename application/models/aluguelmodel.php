<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
class AluguelModel
{
    private $db;
    
    function __construct($db) 
    {
        try {
            $this->db = $db;
        } catch (PDOException $e) {
            exit('Database connection could not be established.');
        }
    }
    
    public function getAll()
    {
        $sql = 'SELECT * FROM Aluga as a
               INNER JOIN livro as l ON l.idLivro = a.Livro_idLivro 
               WHERE a.Cliente_idCliente = :Cliente_idCliente';
        
        $query = $this->db->prepare($sql);
             //var_dump($livros); die;
        $query->bindValue(':Cliente_idCliente', $_SESSION['cliente_id'], PDO::PARAM_INT);
        
        $query->execute();
        
        return $query->fetchAll();
    }
    
    public function getAllAdmin()
    {
        $sql = 'SELECT a.*, l.*,c.*, pf.Nome as NomeCliente, pf.CPF  FROM Aluga as a
               INNER JOIN livro as l ON l.idLivro = a.Livro_idLivro 
               INNER JOIN cliente as c ON c.idCliente = a.Cliente_idCliente 
               LEFT JOIN pessoafisica as pf ON pf.Cliente_idCliente = c.idCliente 
               LEFT JOIN pessoajuridica as pj ON pj.Cliente_idCliente = c.idCliente';
        
        $query = $this->db->prepare($sql);
        
        $query->execute();
        
        return $query->fetchAll();
    }
    
    public function renovar($id_aluga)
    {
        $sql = "UPDATE Aluga SET
               DataDevolucao = :DataDevolucao
               WHERE idAluga = :idAluga";     
        
        $query = $this->db->prepare($sql); 

        $query->bindValue(':DataDevolucao', date('y/m/d', strtotime("+13 days")));
        $query->bindValue(':idAluga', $id_aluga);
        
        if ($query->execute()){
            return true;
        }
        
        return false;
    }
    
    public function get($id)
    {
        $sql = "SELECT * FROM Aluga as a
                INNER JOIN livro as l ON l.idLivro = a.IdAluga
                WHERE a.idAluga={$id}";
                
        $query = $this->db->prepare($sql);
             //var_dump($livros); die;
        $query->execute();  
        
        $aluguel = $query->fetchAll();
        
        return reset($aluguel);
    } 
    
    function isReserva($livro_id)
    {
        $sql = 'SELECT * FROM reserva as r 
               INNER JOIN livro as l ON l.idLivro = r.Livro_idLivro 
               WHERE r.Livro_idLivro = :Livro_idLivro';

        $query = $this->db->prepare($sql); 
       //echo $_SESSION['cliente_id']; die;
        $query->bindValue(':Livro_idLivro',$livro_id);

        $query->execute();
        
        if ($query->rowCount()){
            return true;
        }
        
        return false;
    }
    
   function livrosNaoDevolvidos()
   {
        $sql = 'SELECT * FROM Aluga as a
               INNER JOIN livro as l ON l.idLivro = a.Livro_idLivro 
               WHERE a.DataDevolucao < :data_atual';
        
         $query = $this->db->prepare($sql); 
         
         $query->bindValue(':data_atual', date('y/m/d'));
         
         $query->execute();
             
         return $query->fetchAll();
   }
   
   function aplicaMulta($aluguel_id, $data_devolucao)
   {
       $dias = $this->calculaDiferenca($data_devolucao, date('y-m-d'));
       //echo $dias;
       $multa = 1.0 * $dias;
       
       //echo $multa; die;
       
        $sql = "UPDATE Aluga SET
               ValorMulta = :ValorMulta 
               WHERE idAluga = :idAluga";     
        
        $query = $this->db->prepare($sql); 

        $query->bindValue(':idAluga',$aluguel_id);
        $query->bindValue(':ValorMulta', $multa);
        
        if ($query->execute()){
            return true;
        }
        return false;
   }
   
  private function calculaDiferenca($data_inicial, $data_final)
  {
        $diferenca = strtotime($data_final) - strtotime($data_inicial);
        $dias = floor($diferenca / (60 * 60 * 24));
        return $dias;
  }

  function add($reserva_id, $cliente_id, $livro_id, $preco_aluguel)
  {
     
        $this->db->beginTransaction();
        $query = $this->db->prepare('INSERT INTO pedidos (valor_total) VALUES (0)');
        $query->execute();
        
        $pedido_id = $this->db->lastInsertId();
        
        $sql = "INSERT INTO Aluga " 
               . "(pedido_id, DataAluguel, ValorAluguel,ValorMulta,DataDevolucao,Cliente_idCLiente,Livro_idLivro) "
               . " VALUES ("
               . ":pedido_id, "
               . ":DataAluguel, "
               . ":ValorAluguel, "
               . ":ValorMulta, "
               . ":DataDevolucao, "
               . ":Cliente_idCLiente, "
               . ":Livro_idLivro)";
        
        $query = $this->db->prepare($sql);
        
        $query->bindValue(':pedido_id', $pedido_id, PDO::PARAM_INT);
        $query->bindValue(':DataAluguel', date("y/m/d"), PDO::PARAM_STR);
        $query->bindValue(':ValorAluguel', (float)$preco_aluguel);
        $query->bindValue(':ValorMulta',(float)0.0);
        $query->bindValue(':DataDevolucao', date('y/m/d', strtotime("+13 days")),  PDO::PARAM_STR);
        $query->bindValue(':Cliente_idCLiente', $cliente_id, PDO::PARAM_INT);
        $query->bindValue(':Livro_idLivro', $livro_id, PDO::PARAM_INT);  
        
        if ($query->execute()){
            
            $sql = "DELETE FROM reserva WHERE idReserva = :idReserva";            
            $stm = $this->db->prepare($sql);
            $stm->bindValue(':idReserva', $reserva_id, PDO::PARAM_INT);
            if ($stm->execute()){
                $this->db->commit();
                return true;               
            }

        }
        $this->db->rollback();
        return false;
  }
  
}